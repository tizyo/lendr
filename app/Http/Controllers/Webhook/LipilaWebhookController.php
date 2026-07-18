<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lipila webhook — HMAC-SHA256, header: X-Lipila-Signature.
 */
class LipilaWebhookController extends BaseWebhookController
{
    protected function providerName(): string
    {
        return 'lipila';
    }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'lipila_webhook_secret')->value('value');

        if (! $secret) {
            Log::warning('[Webhook:lipila] No webhook secret configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        return hash_equals(
            hash_hmac('sha256', $request->getContent(), $secret),
            $request->header('X-Lipila-Signature', ''),
        );
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();

        $rawStatus = strtolower($body['status'] ?? $body['transaction_status'] ?? '');
        $status = match ($rawStatus) {
            'success', 'successful', 'completed' => 'success',
            'failed', 'failure' => 'failed',
            default => 'pending',
        };

        return [
            'event_id' => $body['transaction_id'] ?? $body['id'] ?? null,
            'event_type' => 'payment.'.$status,
            'internal_ref' => $body['reference'] ?? $body['order_ref'] ?? null,
            'transaction_id' => $body['transaction_id'] ?? $body['id'] ?? '',
            'amount' => (float) ($body['amount'] ?? 0),
            'phone' => $body['phone'] ?? $body['msisdn'] ?? '',
            'status' => $status,
            'raw' => $body,
        ];
    }
}
