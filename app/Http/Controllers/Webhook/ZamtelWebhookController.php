<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Zamtel Kwacha webhook — HMAC-SHA256, header: X-Zamtel-Signature.
 * Reference is in the ref/reference field.
 */
class ZamtelWebhookController extends BaseWebhookController
{
    protected function providerName(): string { return 'zamtel_kwacha'; }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'zamtel_webhook_secret')->value('value');
        if (! $secret) return true;

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $request->header('X-Zamtel-Signature', '')
        );
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();

        $rawStatus = strtolower($body['status'] ?? $body['transactionStatus'] ?? '');
        $status = match ($rawStatus) {
            'success', 'successful', 'completed' => 'success',
            'failed', 'failure', 'error'         => 'failed',
            default                              => 'pending',
        };

        return [
            'event_id'       => $body['transactionId'] ?? $body['id']  ?? null,
            'event_type'     => 'payment.' . $status,
            'internal_ref'   => $body['reference']     ?? $body['ref'] ?? null,
            'transaction_id' => $body['transactionId'] ?? $body['id']  ?? '',
            'amount'         => (float) ($body['amount']               ?? 0),
            'phone'          => $body['msisdn']        ?? $body['phone'] ?? '',
            'status'         => $status,
            'raw'            => $body,
        ];
    }
}
