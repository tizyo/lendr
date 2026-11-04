<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * MTN MoMo webhook — API key verification via X-MTN-Callback-Api-Key header.
 * Reference is in externalId field set during collection initiation.
 */
class MtnWebhookController extends BaseWebhookController
{
    protected function providerName(): string
    {
        return 'mtn_momo';
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = \App\Support\TenantSettings::get('mtn_callback_api_key');

        if (! $secret) {
            Log::warning('[Webhook:mtn_momo] No callback API key configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        // MTN signs nothing and can't add headers to its callbacks, so the key also
        // travels in the callback URL we give it (X-Callback-Url, see
        // InitiateMobileMoneyPaymentJob). Either way it must match exactly.
        $given = (string) ($request->header('X-MTN-Callback-Api-Key') ?: $request->query('key', ''));

        return $given !== '' && hash_equals($secret, $given);
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();

        $rawStatus = strtolower($body['status'] ?? '');
        $status = match ($rawStatus) {
            'successful', 'success' => 'success',
            'failed' => 'failed',
            default => 'pending',
        };

        return [
            'event_id' => $body['financialTransactionId'] ?? $body['referenceId'] ?? null,
            'event_type' => 'payment.'.$status,
            'internal_ref' => $body['externalId'] ?? null,
            'transaction_id' => $body['financialTransactionId'] ?? $body['referenceId'] ?? '',
            'amount' => (float) ($body['amount'] ?? 0),
            'phone' => $body['payer']['partyId'] ?? '',
            'status' => $status,
            'raw' => $body,
        ];
    }
}
