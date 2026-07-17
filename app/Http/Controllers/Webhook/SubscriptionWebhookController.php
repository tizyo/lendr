<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Billing\BillingGatewayManager;
use App\Services\Billing\BillingService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Handles inbound billing/subscription webhooks from payment gateways.
 *
 * These operate in the **central (landlord) database context** — no tenant
 * initialisation is needed here. The route lives at:
 *   POST /api/webhooks/subscription/{gateway}
 *
 * The {gateway} segment is used to select the right driver for signature
 * verification, then BillingService handles the business logic.
 */
class SubscriptionWebhookController extends Controller
{
    public function __construct(
        private readonly BillingGatewayManager $gateways,
        private readonly BillingService $billing,
    ) {}

    public function handle(Request $request, string $gateway): Response
    {
        // Resolve the driver (doesn't require an active gateway — any configured one)
        try {
            $driver = $this->gateways->driver($gateway);
        } catch (\Throwable $e) {
            Log::warning("[SubWebhook] Unknown gateway: {$gateway}");
            return response()->noContent(400);
        }

        // Signature verification
        if (! $driver->verifyWebhookSignature($request)) {
            Log::warning("[SubWebhook:{$gateway}] Invalid signature", ['ip' => $request->ip()]);
            return response()->noContent(401);
        }

        // Parse into normalised payload
        $payload = $driver->parseWebhookPayload($request);

        if (empty($payload['tx_ref'])) {
            // Not a subscription payment (e.g. a test ping) — ignore silently
            return response()->noContent();
        }

        // Only process subscription references
        if (! str_starts_with($payload['tx_ref'], 'LENDR-SUB-')) {
            return response()->noContent(); // unrelated transaction
        }

        try {
            $this->billing->handleWebhook(
                txRef:         $payload['tx_ref'],
                transactionId: $payload['transaction_id'],
                status:        $payload['status'],
                amount:        $payload['amount'],
            );
        } catch (\Throwable $e) {
            Log::error("[SubWebhook:{$gateway}] Error processing webhook", [
                'error'  => $e->getMessage(),
                'tx_ref' => $payload['tx_ref'],
            ]);
        }

        return response()->noContent();
    }
}
