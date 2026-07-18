<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Loan;
use App\Models\Tenant\MobileMoneyIntent;
use App\Models\Tenant\MobileMoneyTransaction;
use App\Models\Tenant\StandingOrder;
use App\Models\Tenant\WebhookEvent;
use App\Services\Payment\AutoDebitService;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseWebhookController extends Controller
{
    public function __construct(
        protected PaymentService $payments,
        protected AutoDebitService $autoDebit,
    ) {}

    // ─── Each provider must implement ────────────────────────────────────────

    abstract protected function providerName(): string;

    /** Return true if the request signature is valid. */
    abstract protected function verifySignature(Request $request): bool;

    /**
     * Parse the incoming payload into a normalised array:
     * [
     *   'event_id'       => string,   // provider event / transaction ID (for idempotency)
     *   'event_type'     => string,   // e.g. 'payment.success'
     *   'internal_ref'   => string,   // LENDR reference (LENDR-XXXXXXXXXX)
     *   'transaction_id' => string,   // provider's own transaction ID
     *   'amount'         => float,
     *   'phone'          => string,
     *   'status'         => 'success'|'failed'|'pending',
     *   'raw'            => array,    // full raw payload
     * ]
     */
    abstract protected function parsePayload(Request $request): array;

    // ─── Shared handle flow ───────────────────────────────────────────────────

    public function handle(Request $request): Response
    {
        if (! $this->ipAllowed($request)) {
            Log::warning("[Webhook:{$this->providerName()}] Source IP not in configured allowlist", ['ip' => $request->ip()]);

            return response()->noContent(403);
        }

        if (! $this->verifySignature($request)) {
            Log::warning("[Webhook:{$this->providerName()}] Invalid signature", ['ip' => $request->ip()]);

            return response()->noContent(401);
        }

        $payload = $this->parsePayload($request);

        // Idempotency — skip already-processed events
        $event = $this->logEvent($payload);
        if ($event === null) {
            return response()->noContent(); // duplicate
        }

        try {
            $this->processPayload($payload, $event);
            $event->markProcessed();
        } catch (\Throwable $e) {
            $event->markFailed($e->getMessage());
            Log::error("[Webhook:{$this->providerName()}] Processing error", [
                'error' => $e->getMessage(),
                'ref' => $payload['internal_ref'] ?? null,
            ]);
        }

        return response()->noContent();
    }

    // ─── Core processing ─────────────────────────────────────────────────────

    private function processPayload(array $payload, WebhookEvent $event): void
    {
        $internalRef = $payload['internal_ref'] ?? null;
        $status = $payload['status'] ?? 'unknown';

        // Route LENDR-DEBIT-* references to standing orders (Phase 55)
        if ($internalRef && str_starts_with($internalRef, 'LENDR-DEBIT-')) {
            $this->processAutoDebit($internalRef, $status, $payload);

            return;
        }

        // Find the MobileMoneyIntent created by ProfileController::initiatePayment
        $intent = $internalRef
            ? MobileMoneyIntent::where('reference', $internalRef)->first()
            : null;

        if (! $intent) {
            Log::info("[Webhook:{$this->providerName()}] Unknown ref, logging only", ['ref' => $internalRef]);
            $event->update(['status' => 'skipped']);

            return;
        }

        // Update / create MobileMoneyTransaction
        MobileMoneyTransaction::updateOrCreate(
            ['internal_ref' => $internalRef],
            [
                'provider' => $this->providerName(),
                'transaction_id' => $payload['transaction_id'],
                'transactable_type' => MobileMoneyIntent::class,
                'transactable_id' => $intent->id,
                'phone' => $payload['phone'] ?? $intent->phone,
                'amount' => $payload['amount'] ?? $intent->amount,
                'currency' => 'ZMW',
                'direction' => 'inbound',
                'status' => $status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : 'processing'),
                'provider_response' => json_encode($payload['raw']),
                'processed_at' => $status === 'success' ? now() : null,
            ],
        );

        if ($status !== 'success') {
            $intent->update(['status' => 'failed']);

            return;
        }

        if ($intent->status === 'confirmed') {
            return; // already handled
        }

        // Record the payment
        $loan = Loan::find($intent->loan_id);
        if (! $loan) {
            $intent->update(['status' => 'failed']);

            return;
        }

        $payment = $this->payments->record($loan, [
            'amount' => (float) $intent->amount,
            'payment_method' => $intent->provider,
            'payment_date' => now()->toDateString(),
            'source' => 'mobile_money_webhook',
            'reference' => $internalRef,
            'momo_transaction_id' => $payload['transaction_id'],
            'momo_provider' => $this->providerName(),
            'notes' => "Self-service payment via {$this->providerName()}",
        ]);

        $intent->update([
            'status' => 'confirmed',
            'provider_transaction_id' => $payload['transaction_id'],
            'payment_id' => $payment->id,
        ]);
    }

    // ─── Auto-debit (standing order) handling ────────────────────────────────

    private function processAutoDebit(string $ref, string $status, array $payload): void
    {
        // ref format: LENDR-DEBIT-{order_id}-{timestamp}
        $parts = explode('-', $ref);
        $orderId = $parts[2] ?? null;

        if (! $orderId) {
            Log::warning("[Webhook:{$this->providerName()}] Malformed DEBIT ref", ['ref' => $ref]);

            return;
        }

        $order = StandingOrder::find($orderId);
        if (! $order) {
            Log::info("[Webhook:{$this->providerName()}] Standing order not found", ['ref' => $ref]);

            return;
        }

        if ($status === 'success') {
            $this->autoDebit->confirmFromWebhook($order, $payload);
        } elseif ($status === 'failed') {
            $order->recordFailure("Webhook reported failure for ref {$ref}");
            Log::info("[Webhook:{$this->providerName()}] Standing order debit failed", ['ref' => $ref, 'order_id' => $orderId]);
        }
    }

    /**
     * Optional per-provider IP allowlist, configured via the tenant's
     * settings table (comma-separated IPs/CIDRs, e.g. "41.223.1.0/24,102.66.0.5").
     * Providers without a mobile-money publisher IP list configured pass
     * through untouched — signature verification remains the primary control.
     */
    private function ipAllowed(Request $request): bool
    {
        $raw = DB::table('settings')->where('key', "{$this->providerName()}_webhook_ip_allowlist")->value('value');

        if (! $raw) {
            return true;
        }

        $ip = $request->ip();

        foreach (array_filter(array_map('trim', explode(',', $raw))) as $entry) {
            if (str_contains($entry, '/')) {
                if ($this->ipInCidr($ip, $entry)) {
                    return true;
                }
            } elseif (hash_equals($entry, $ip)) {
                return true;
            }
        }

        return false;
    }

    private function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);
        $bits = (int) $bits;

        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bytes = intdiv($bits, 8);
        $remBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remBits) & 0xFF;

        return (ord($ipBin[$bytes]) & $mask) === (ord($subnetBin[$bytes]) & $mask);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Log webhook event. Returns null if already processed (duplicate).
     */
    private function logEvent(array $payload): ?WebhookEvent
    {
        $eventId = $payload['event_id'] ?? null;
        if (! $eventId) {
            // No event ID — always process (provider doesn't send one)
            return WebhookEvent::create([
                'provider' => $this->providerName(),
                'event_id' => uniqid($this->providerName().'-', true),
                'event_type' => $payload['event_type'] ?? 'unknown',
                'payload' => $payload['raw'] ?? $payload,
                'status' => 'received',
            ]);
        }

        // Idempotency check
        $existing = WebhookEvent::where('event_id', $eventId)->first();
        if ($existing && $existing->status === 'processed') {
            return null;
        }

        return WebhookEvent::updateOrCreate(
            ['event_id' => $eventId],
            [
                'provider' => $this->providerName(),
                'event_type' => $payload['event_type'] ?? 'unknown',
                'payload' => $payload['raw'] ?? $payload,
                'status' => 'received',
            ],
        );
    }
}
