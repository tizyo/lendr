<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MTN MoMo webhook — API key verification via X-MTN-Callback-Api-Key header.
 * Reference is in externalId field set during collection initiation.
 */
class MtnWebhookController extends BaseWebhookController
{
    protected function providerName(): string { return 'mtn_momo'; }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'mtn_callback_api_key')->value('value');

        if (! $secret) {
            Log::warning('[Webhook:mtn_momo] No callback API key configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        // MTN uses a static callback API key rather than HMAC
        return hash_equals($secret, $request->header('X-MTN-Callback-Api-Key', ''));
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();

        $rawStatus = strtolower($body['status'] ?? '');
        $status = match ($rawStatus) {
            'successful', 'success' => 'success',
            'failed'                => 'failed',
            default                 => 'pending',
        };

        return [
            'event_id'       => $body['financialTransactionId'] ?? $body['referenceId'] ?? null,
            'event_type'     => 'payment.' . $status,
            'internal_ref'   => $body['externalId']            ?? null,
            'transaction_id' => $body['financialTransactionId'] ?? $body['referenceId'] ?? '',
            'amount'         => (float) ($body['amount']       ?? 0),
            'phone'          => $body['payer']['partyId']      ?? '',
            'status'         => $status,
            'raw'            => $body,
        ];
    }
}
