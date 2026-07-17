<?php

namespace App\Http\Controllers\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Flutterwave webhook — verif-hash header must equal the configured secret.
 * Reference is in meta.internal_ref set during payment initiation.
 */
class FlutterwaveWebhookController extends BaseWebhookController
{
    protected function providerName(): string { return 'flutterwave'; }

    protected function verifySignature(Request $request): bool
    {
        $secret = DB::table('settings')->where('key', 'flutterwave_webhook_secret')->value('value');

        if (! $secret) {
            Log::warning('[Webhook:flutterwave] No webhook secret configured — rejecting unsigned request', ['ip' => $request->ip()]);

            return false;
        }

        return hash_equals($secret, $request->header('verif-hash', ''));
    }

    protected function parsePayload(Request $request): array
    {
        $body = $request->json()->all();
        $data = $body['data'] ?? [];

        $rawStatus = strtolower($data['status'] ?? '');
        $status = match ($rawStatus) {
            'successful', 'success' => 'success',
            'failed'                => 'failed',
            default                 => 'pending',
        };

        // LENDR reference set as tx_ref during initiation
        $internalRef = $data['tx_ref'] ?? $data['meta']['internal_ref'] ?? null;

        return [
            'event_id'       => (string) ($data['id']     ?? ''),
            'event_type'     => $body['event']             ?? 'payment.' . $status,
            'internal_ref'   => $internalRef,
            'transaction_id' => (string) ($data['id']     ?? ''),
            'amount'         => (float) ($data['amount']  ?? 0),
            'phone'          => $data['customer']['phone_number'] ?? '',
            'status'         => $status,
            'raw'            => $body,
        ];
    }
}
