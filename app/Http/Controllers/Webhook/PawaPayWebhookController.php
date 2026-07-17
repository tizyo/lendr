<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * PawaPay webhook — HMAC-SHA256 of raw body, header: X-PawaPay-Signature.
 */
class PawaPayWebhookController extends BaseWebhookController
{
    protected function providerName(): string { return 'pawapay'; }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'pawapay_webhook_secret')->value('value');

        if (! $secret) {
            Log::warning('[Webhook:pawapay] No webhook secret configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $request->header('X-PawaPay-Signature', '')
        );
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();

        $status = match (strtolower($body['status'] ?? '')) {
            'completed'                                => 'success',
            'failed', 'rejected', 'duplicate_ignored' => 'failed',
            default                                    => 'pending',
        };

        return [
            'event_id'       => $body['paymentId']                ?? null,
            'event_type'     => 'payment.' . $status,
            'internal_ref'   => $body['statementDescription']     ?? null,
            'transaction_id' => $body['paymentId']                ?? '',
            'amount'         => (float) ($body['amount']          ?? 0),
            'phone'          => $body['payer']['address']['value'] ?? '',
            'status'         => $status,
            'raw'            => $body,
        ];
    }
}
