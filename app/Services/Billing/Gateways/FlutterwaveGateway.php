<?php

namespace App\Services\Billing\Gateways;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FlutterwaveGateway implements BillingGatewayInterface
{
    private const BASE = 'https://api.flutterwave.com/v3';

    public function __construct(private readonly BillingGatewayConfig $config) {}

    public function getName(): string { return 'flutterwave'; }

    /**
     * Create a hosted payment link via Flutterwave Standard checkout.
     */
    public function initiatePayment(array $payload): string
    {
        $response = Http::withToken($this->secretKey())
            ->post(self::BASE . '/payments', $payload)
            ->throw();

        $data = $response->json();

        if (($data['status'] ?? '') !== 'success') {
            throw new RuntimeException('Flutterwave payment initiation failed: ' . ($data['message'] ?? 'Unknown error'));
        }

        return $data['data']['link'];
    }

    /**
     * Verify a transaction by Flutterwave transaction ID.
     */
    public function verifyPayment(string $transactionId): array
    {
        $response = Http::withToken($this->secretKey())
            ->get(self::BASE . "/transactions/{$transactionId}/verify")
            ->throw();

        $data = $response->json('data', []);

        $rawStatus = strtolower($data['status'] ?? '');
        $status = match ($rawStatus) {
            'successful', 'success' => 'success',
            'failed'                => 'failed',
            default                 => 'pending',
        };

        return [
            'status'   => $status,
            'amount'   => (float) ($data['amount'] ?? 0),
            'currency' => $data['currency'] ?? 'ZMW',
            'tx_ref'   => $data['tx_ref'] ?? '',
        ];
    }

    /**
     * Verify the `verif-hash` header against the configured webhook secret.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->webhookSecret();

        if (! $secret) {
            Log::warning('[BillingWebhook:flutterwave] No webhook secret configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        return hash_equals($secret, $request->header('verif-hash', ''));
    }

    /**
     * Parse Flutterwave webhook body into normalised shape.
     */
    public function parseWebhookPayload(Request $request): array
    {
        $body = $request->json()->all();
        $data = $body['data'] ?? [];

        $rawStatus = strtolower($data['status'] ?? '');
        $status = match ($rawStatus) {
            'successful', 'success' => 'success',
            'failed'                => 'failed',
            default                 => 'pending',
        };

        return [
            'event_id'       => (string) ($data['id'] ?? uniqid('fw-', true)),
            'event_type'     => $body['event'] ?? 'charge.' . $status,
            'tx_ref'         => $data['tx_ref'] ?? '',
            'transaction_id' => (string) ($data['id'] ?? ''),
            'amount'         => (float) ($data['amount'] ?? 0),
            'status'         => $status,
            'raw'            => $body,
        ];
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function secretKey(): string
    {
        return $this->config->secret_key ?? '';
    }

    private function webhookSecret(): ?string
    {
        return $this->config->webhook_secret ?: null;
    }
}
