<?php

namespace App\Services\Billing\Contracts;

use Illuminate\Http\Request;

interface BillingGatewayInterface
{
    /**
     * Initiate a hosted payment checkout.
     *
     * @param  array{
     *   tx_ref:        string,
     *   amount:        float,
     *   currency:      string,
     *   redirect_url:  string,
     *   customer:      array{email: string, name: string},
     *   meta:          array,
     *   customizations?: array,
     * } $payload
     * @return string The redirect URL to send the user to.
     */
    public function initiatePayment(array $payload): string;

    /**
     * Verify a transaction by the gateway's own transaction ID.
     *
     * @return array{status: string, amount: float, currency: string, tx_ref: string}
     */
    public function verifyPayment(string $transactionId): array;

    /**
     * Verify the webhook signature for an incoming request.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Parse the webhook body into a normalised array.
     *
     * @return array{
     *   event_id:       string,
     *   event_type:     string,
     *   tx_ref:         string,
     *   transaction_id: string,
     *   amount:         float,
     *   status:         string,
     *   raw:            array,
     * }
     */
    public function parseWebhookPayload(Request $request): array;

    /** Driver name slug, e.g. "flutterwave". */
    public function getName(): string;
}
