<?php

namespace App\Services\Payment;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\DisbursementLog;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Asks the provider what finally happened to a payout it accepted.
 *
 * "Accepted" only means the provider queued the transfer; it can still fail
 * (wrong/blocked wallet, provider float, limits). Returns one of:
 *   completed — money reached the borrower
 *   failed    — definitively failed after acceptance (no money moved)
 *   pending   — still in progress
 *   unknown   — provider can't be asked (no status API / no credentials / error)
 *
 * Credentials come from the same place the payout was sent with: the
 * Enterprise TenantWallet (used_wallet) or the tenant's settings.
 *
 * @phpstan-type Outcome array{state: 'completed'|'failed'|'pending'|'unknown', reason: ?string}
 */
class PayoutStatusChecker
{
    /** @return Outcome */
    public function check(DisbursementLog $log): array
    {
        if (! $log->provider_reference) {
            return $this->unknown('No provider reference was stored for this payout.');
        }

        try {
            return match ($log->gateway) {
                'pawapay' => $this->pawapay($log),
                'mtn_momo' => $this->mtn($log),
                'airtel_money' => $this->airtel($log),
                'flutterwave' => $this->flutterwave($log),
                default => $this->unknown(ucwords(str_replace('_', ' ', $log->gateway)).' has no payout status API — confirm in the provider portal.'),
            };
        } catch (\Throwable $e) {
            return $this->unknown('Status check failed: '.$e->getMessage());
        }
    }

    // ─── Providers ────────────────────────────────────────────────────────────

    /** @return Outcome */
    private function pawapay(DisbursementLog $log): array
    {
        $c = $this->credentials($log);
        if (! $c['key']) {
            return $this->unknown('PawaPay credentials are not configured.');
        }

        $base = $c['production'] ? 'https://api.pawapay.io' : 'https://api.sandbox.pawapay.cloud';
        $response = $this->ok(Http::withToken($c['key'])->timeout(20)->get("{$base}/payouts/{$log->provider_reference}"));

        // v1 answers with a list holding the one payout.
        $payout = array_is_list($response->json() ?? []) ? ($response->json()[0] ?? null) : $response->json();

        if (! $payout) {
            return $this->unknown('PawaPay has no record of this payout.');
        }

        return match (strtoupper((string) ($payout['status'] ?? ''))) {
            'COMPLETED' => $this->state('completed'),
            'FAILED' => $this->state('failed', data_get($payout, 'failureReason.failureMessage') ?? data_get($payout, 'failureReason.failureCode')),
            default => $this->state('pending'),
        };
    }

    /** @return Outcome */
    private function mtn(DisbursementLog $log): array
    {
        $c = $this->credentials($log);
        if (! $c['subscription_key'] || ! $c['api_user'] || ! $c['api_key']) {
            return $this->unknown('MTN credentials are not configured.');
        }

        $base = $c['production'] ? 'https://proxy.momoapi.mtn.com' : 'https://sandbox.momodeveloper.mtn.com';
        $env = $c['production'] ? 'production' : 'sandbox';

        $token = $this->ok(Http::timeout(20)
            ->withHeaders(['Ocp-Apim-Subscription-Key' => $c['subscription_key']])
            ->withBasicAuth($c['api_user'], $c['api_key'])
            ->post("{$base}/disbursement/token/"))->json('access_token');

        $response = $this->ok(Http::withToken($token)->timeout(20)
            ->withHeaders(['X-Target-Environment' => $c['target_environment'] ?? $env, 'Ocp-Apim-Subscription-Key' => $c['subscription_key']])
            ->get("{$base}/disbursement/v1_0/transfer/{$log->provider_reference}"));

        $reason = $response->json('reason');

        return match (strtoupper((string) $response->json('status'))) {
            'SUCCESSFUL' => $this->state('completed'),
            'FAILED', 'REJECTED' => $this->state('failed', is_array($reason) ? ($reason['message'] ?? $reason['code'] ?? null) : $reason),
            default => $this->state('pending'),
        };
    }

    /** @return Outcome */
    private function airtel(DisbursementLog $log): array
    {
        $c = $this->credentials($log);
        if (! $c['client_id'] || ! $c['client_secret']) {
            return $this->unknown('Airtel credentials are not configured.');
        }

        $base = $c['production'] ? 'https://openapi.airtel.africa' : 'https://openapiuat.airtel.africa';

        $token = $this->ok(Http::timeout(20)->post("{$base}/auth/oauth2/token", [
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'grant_type' => 'client_credentials',
        ]))->json('access_token');

        // Airtel looks payouts up by the transaction id we sent (our reference).
        $response = $this->ok(Http::withToken($token)->timeout(20)
            ->withHeaders(['X-Country' => 'ZM', 'X-Currency' => 'ZMW'])
            ->get("{$base}/standard/v1/disbursements/{$log->reference}"));

        return match (strtoupper((string) $response->json('data.transaction.status'))) {
            'TS' => $this->state('completed'),
            'TF' => $this->state('failed', $response->json('data.transaction.message') ?? $response->json('status.message')),
            default => $this->state('pending'), // TIP (in progress), TA (ambiguous)
        };
    }

    /** @return Outcome */
    private function flutterwave(DisbursementLog $log): array
    {
        $c = $this->credentials($log);
        if (! $c['key']) {
            return $this->unknown('Flutterwave credentials are not configured.');
        }

        $response = $this->ok(Http::withToken($c['key'])->timeout(20)
            ->get("https://api.flutterwave.com/v3/transfers/{$log->provider_reference}"));

        return match (strtoupper((string) $response->json('data.status'))) {
            'SUCCESSFUL' => $this->state('completed'),
            'FAILED' => $this->state('failed', $response->json('data.complete_message')),
            default => $this->state('pending'),
        };
    }

    // ─── Credentials (same sources the payout was sent with) ─────────────────

    private function credentials(DisbursementLog $log): array
    {
        if ($log->used_wallet) {
            $w = TenantWallet::forTenant((string) tenant('id'));

            return [
                'production' => $w?->environment === 'production',
                'key' => $w?->api_key,
                'client_id' => $w?->api_key,
                'client_secret' => $w?->api_secret,
                'subscription_key' => $w?->meta('mtn_disbursement_subscription_key') ?? $w?->api_key,
                'api_user' => $w?->meta('mtn_api_user') ?? $w?->wallet_id,
                'api_key' => $w?->api_secret ?? $w?->api_key,
                'target_environment' => $w?->environment,
            ];
        }

        $s = DB::table('settings')->pluck('value', 'key');
        $env = fn (string $key) => $s->get($key) === 'production';

        return match ($log->gateway) {
            'pawapay' => ['production' => $env('pawapay_environment'), 'key' => $s->get('pawapay_api_key')],
            'airtel_money' => ['production' => $env('airtel_environment'), 'client_id' => $s->get('airtel_client_id'), 'client_secret' => $s->get('airtel_client_secret')],
            'mtn_momo' => [
                'production' => $env('mtn_environment'),
                'subscription_key' => $s->get('mtn_disbursement_subscription_key') ?? $s->get('mtn_subscription_key'),
                'api_user' => $s->get('mtn_api_user'),
                'api_key' => $s->get('mtn_api_key'),
                'target_environment' => $s->get('mtn_environment', 'sandbox'),
            ],
            default => ['production' => false, 'key' => null],
        } + ['key' => null, 'client_id' => null, 'client_secret' => null, 'subscription_key' => null, 'api_user' => null, 'api_key' => null];
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function ok(Response $response): Response
    {
        return $response->throw();
    }

    /** @return Outcome */
    private function state(string $state, ?string $reason = null): array
    {
        return ['state' => $state, 'reason' => $reason];
    }

    /** @return Outcome */
    private function unknown(string $reason): array
    {
        return $this->state('unknown', $reason);
    }
}
