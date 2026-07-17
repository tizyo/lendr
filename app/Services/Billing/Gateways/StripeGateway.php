<?php

namespace App\Services\Billing\Gateways;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Stripe subscription billing gateway — stub implementation.
 * Credentials are stored/encrypted in billing_gateway_configs.
 * Full API integration can be added here when needed.
 */
class StripeGateway implements BillingGatewayInterface
{
    public function __construct(private readonly BillingGatewayConfig $config) {}

    public function getName(): string { return 'stripe'; }

    public function initiatePayment(array $payload): string
    {
        throw new RuntimeException('Stripe billing gateway is not yet fully integrated.');
    }

    public function verifyPayment(string $transactionId): array
    {
        throw new RuntimeException('Stripe billing gateway is not yet fully integrated.');
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        return true;
    }

    public function parseWebhookPayload(Request $request): array
    {
        return [
            'event_id'       => uniqid('stripe-', true),
            'event_type'     => 'unknown',
            'tx_ref'         => '',
            'transaction_id' => '',
            'amount'         => 0,
            'status'         => 'pending',
            'raw'            => $request->json()->all(),
        ];
    }
}
