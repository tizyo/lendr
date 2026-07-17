<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Airtel Money webhook — HMAC-SHA256, header: X-Airtel-Signature.
 * Internal reference is passed in the `transaction.reference` field.
 */
class AirtelWebhookController extends BaseWebhookController
{
    protected function providerName(): string { return 'airtel_money'; }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'airtel_webhook_secret')->value('value');

        if (! $secret) {
            Log::warning('[Webhook:airtel_money] No webhook secret configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $request->header('X-Airtel-Signature', '')
        );
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();
        $tx   = $body['transaction'] ?? [];

        $rawStatus = strtolower($tx['status'] ?? $body['status'] ?? '');
        $status = match ($rawStatus) {
            'ts', 'success', 'successful' => 'success',
            'tf', 'fail', 'failed'        => 'failed',
            default                       => 'pending',
        };

        return [
            'event_id'       => $tx['id']        ?? $body['id']        ?? null,
            'event_type'     => 'payment.' . $status,
            'internal_ref'   => $tx['reference'] ?? $body['reference'] ?? null,
            'transaction_id' => $tx['id']        ?? '',
            'amount'         => (float) ($tx['amount'] ?? $body['amount'] ?? 0),
            'phone'          => $tx['msisdn']    ?? $body['msisdn']    ?? '',
            'status'         => $status,
            'raw'            => $body,
        ];
    }
}
