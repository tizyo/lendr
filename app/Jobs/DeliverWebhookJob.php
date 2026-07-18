<?php

namespace App\Jobs;

use App\Models\Tenant\WebhookDelivery;
use App\Models\Tenant\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60; // seconds between retries

    public function __construct(public readonly int $deliveryId) {}

    public function handle(): void
    {
        $delivery = WebhookDelivery::find($this->deliveryId);

        if (! $delivery || $delivery->status === 'success') {
            return;
        }

        $endpoint = WebhookEndpoint::find($delivery->webhook_endpoint_id);

        if (! $endpoint || ! $endpoint->is_active) {
            $delivery->update(['status' => 'failed', 'response_body' => 'Endpoint inactive or deleted.']);

            return;
        }

        $payload = json_encode([
            'event' => $delivery->event,
            'timestamp' => now()->toIso8601String(),
            'data' => $delivery->payload,
        ]);

        $signature = $endpoint->sign($payload);

        $delivery->increment('attempts');
        $endpoint->update(['last_triggered_at' => now()]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Lendr-Event' => $delivery->event,
                    'X-Lendr-Signature' => 'sha256='.$signature,
                ])
                ->send('POST', $endpoint->url, ['body' => $payload]);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'success',
                    'response_code' => $response->status(),
                    'response_body' => substr($response->body(), 0, 1000),
                    'delivered_at' => now(),
                ]);

                $endpoint->update([
                    'failure_count' => 0,
                    'last_success_at' => now(),
                ]);
            } else {
                $this->markFailed($delivery, $endpoint, $response->status(), $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning("Webhook delivery #{$delivery->id} failed: ".$e->getMessage());
            $this->markFailed($delivery, $endpoint, null, $e->getMessage());
        }
    }

    private function markFailed(
        WebhookDelivery $delivery,
        WebhookEndpoint $endpoint,
        ?int $code,
        string $body,
    ): void {
        $isFinal = $delivery->attempts >= $this->tries;

        $delivery->update([
            'status' => $isFinal ? 'failed' : 'pending',
            'response_code' => $code,
            'response_body' => substr($body, 0, 1000),
            'next_retry_at' => $isFinal ? null : now()->addSeconds($this->backoff * $delivery->attempts),
        ]);

        if ($isFinal) {
            $endpoint->increment('failure_count');

            // Auto-disable endpoint after 10 consecutive failures
            if ($endpoint->failure_count >= 10) {
                $endpoint->update(['is_active' => false]);
            }
        }
    }
}
