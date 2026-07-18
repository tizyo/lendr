<?php

namespace App\Jobs;

use App\Models\Tenant\Loan;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Calls the provider's payout/disbursement API to send loan funds to the borrower.
 * Dispatched from LoanController::disburse() when disbursement_method is a mobile money provider.
 *
 * Supported providers: airtel_money, mtn_momo, zamtel_kwacha, pawapay.
 *
 * Settings keys consumed (from tenant settings table):
 *   pawapay:       pawapay_api_key, pawapay_environment (sandbox|production)
 *   airtel_money:  airtel_client_id, airtel_client_secret, airtel_environment (sandbox|production)
 *   mtn_momo:      mtn_disbursement_subscription_key, mtn_api_user, mtn_api_key, mtn_environment (sandbox|production)
 *   zamtel_kwacha: zamtel_api_key, zamtel_api_url
 */
class DisburseMobileMoneyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly Loan $loan) {}

    public function handle(): void
    {
        $loan = $this->loan->loadMissing(['borrower:id,phone,first_name']);

        $phone = $loan->disbursement_account ?? $loan->borrower?->phone;

        if (! $phone) {
            Log::warning('[MoMo:disburse] No target phone for loan disbursement', [
                'loan_id' => $loan->id,
            ]);

            return;
        }

        try {
            $provider = $loan->disbursement_method?->value;

            match ($provider) {
                'airtel_money' => $this->airtel($loan, $phone),
                'mtn_momo' => $this->mtn($loan, $phone),
                'zamtel_kwacha' => $this->zamtel($loan, $phone),
                'pawapay' => $this->pawapay($loan, $phone),
                default => $this->unsupported($loan),
            };
        } catch (\Throwable $e) {
            Log::error('[MoMo:disburse] Disbursement failed', [
                'loan_id' => $loan->id,
                'provider' => $loan->disbursement_method?->value,
                'error' => $e->getMessage(),
            ]);
            $this->fail($e);
        }
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(Loan $loan, string $phone): void
    {
        $apiKey = $this->setting('pawapay_api_key');
        if (! $apiKey) {
            $this->unsupported($loan, 'pawapay_api_key not configured');

            return;
        }

        $env = $this->setting('pawapay_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

        $reference = $loan->disbursement_reference ?? 'LENDR-LOAN-'.$loan->id;

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/payouts", [
                'payoutId' => $reference,
                'amount' => number_format((float) $loan->principal_amount, 2, '.', ''),
                'currency' => 'ZMW',
                'country' => 'ZMB',
                'correspondent' => $this->pawaPayCorrespondent($phone),
                'recipient' => ['type' => 'MSISDN', 'address' => ['value' => $this->e164($phone)]],
                'customerTimestamp' => now()->toIso8601String(),
                'statementDescription' => "LENDR Loan {$loan->loan_number}",
            ]);

        if ($response->successful()) {
            Log::info('[MoMo:pawapay:disburse] Payout accepted', ['loan_id' => $loan->id, 'ref' => $reference]);

            return;
        }

        throw new \RuntimeException("PawaPay payout failed ({$response->status()}): ".$response->body());
    }

    private function pawaPayCorrespondent(string $phone): string
    {
        $prefix = substr(ltrim($phone, '0'), 0, 2);

        return match (true) {
            in_array($prefix, ['96', '97', '95']) => 'AIRTEL_ZAMBIA',
            in_array($prefix, ['76', '77', '78']) => 'MTN_ZAMBIA',
            in_array($prefix, ['65', '66', '67']) => 'ZAMTEL',
            default => 'AIRTEL_ZAMBIA',
        };
    }

    // ─── Airtel Money ─────────────────────────────────────────────────────────

    private function airtel(Loan $loan, string $phone): void
    {
        $clientId = $this->setting('airtel_client_id');
        $clientSecret = $this->setting('airtel_client_secret');
        if (! $clientId || ! $clientSecret) {
            $this->unsupported($loan, 'Airtel credentials not configured');

            return;
        }

        $env = $this->setting('airtel_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://openapi.airtel.africa'
            : 'https://openapiuat.airtel.africa';

        // Step 1 — obtain Bearer token
        $authResponse = Http::post("{$baseUrl}/auth/oauth2/token", [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if (! $authResponse->successful()) {
            throw new \RuntimeException('Airtel auth failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');

        $reference = $loan->disbursement_reference ?? 'LENDR-LOAN-'.$loan->id;

        // Step 2 — initiate disbursement
        $response = Http::withToken($token)
            ->withHeaders([
                'X-Country' => 'ZM',
                'X-Currency' => 'ZMW',
            ])
            ->post("{$baseUrl}/standard/v1/disbursements/", [
                'payee' => [
                    'msisdn' => ltrim($phone, '0'),
                ],
                'reference' => $reference,
                'pin' => $this->setting('airtel_pin', ''),
                'transaction' => [
                    'amount' => (float) $loan->principal_amount,
                    'id' => $reference,
                    'type' => 'B2C',
                ],
            ]);

        if ($response->successful()) {
            Log::info('[MoMo:airtel:disburse] Disbursement accepted', ['loan_id' => $loan->id, 'ref' => $reference]);

            return;
        }

        throw new \RuntimeException("Airtel disbursement failed ({$response->status()}): ".$response->body());
    }

    // ─── MTN MoMo ─────────────────────────────────────────────────────────────

    private function mtn(Loan $loan, string $phone): void
    {
        // MTN disbursement product uses a separate subscription key
        $subscriptionKey = $this->setting('mtn_disbursement_subscription_key')
            ?? $this->setting('mtn_subscription_key');
        $apiUser = $this->setting('mtn_api_user');
        $apiKey = $this->setting('mtn_api_key');

        if (! $subscriptionKey || ! $apiUser || ! $apiKey) {
            $this->unsupported($loan, 'MTN credentials not configured');

            return;
        }

        $env = $this->setting('mtn_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://proxy.momoapi.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        // Step 1 — obtain Bearer token (disbursement product)
        $authResponse = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->withBasicAuth($apiUser, $apiKey)
            ->post("{$baseUrl}/disbursement/token/");

        if (! $authResponse->successful()) {
            throw new \RuntimeException('MTN disbursement token fetch failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');
        $requestUuid = (string) Str::uuid();
        $reference = $loan->disbursement_reference ?? 'LENDR-LOAN-'.$loan->id;

        // Step 2 — transfer
        $response = Http::withToken($token)
            ->withHeaders([
                'X-Reference-Id' => $requestUuid,
                'X-Target-Environment' => $env,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])
            ->post("{$baseUrl}/disbursement/v1_0/transfer", [
                'amount' => (string) (float) $loan->principal_amount,
                'currency' => 'ZMW',
                'externalId' => $reference,
                'payee' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->e164($phone),
                ],
                'payerMessage' => "LENDR Loan {$loan->loan_number} disbursement",
                'payeeNote' => $reference,
            ]);

        // MTN returns 202 Accepted for queued transfer
        if ($response->status() === 202) {
            Log::info('[MoMo:mtn:disburse] Transfer accepted', ['loan_id' => $loan->id, 'uuid' => $requestUuid]);

            return;
        }

        throw new \RuntimeException("MTN transfer failed ({$response->status()}): ".$response->body());
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(Loan $loan, string $phone): void
    {
        $apiKey = $this->setting('zamtel_api_key');
        $baseUrl = $this->setting('zamtel_api_url');
        if (! $apiKey || ! $baseUrl) {
            $this->unsupported($loan, 'Zamtel credentials not configured');

            return;
        }

        $reference = $loan->disbursement_reference ?? 'LENDR-LOAN-'.$loan->id;

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post(rtrim($baseUrl, '/').'/payments/disburse', [
                'reference' => $reference,
                'msisdn' => $this->e164($phone),
                'amount' => (float) $loan->principal_amount,
                'currency' => 'ZMW',
                'description' => "LENDR Loan {$loan->loan_number} disbursement",
            ]);

        if ($response->successful()) {
            Log::info('[MoMo:zamtel:disburse] Disbursement accepted', ['loan_id' => $loan->id, 'ref' => $reference]);

            return;
        }

        throw new \RuntimeException("Zamtel disbursement failed ({$response->status()}): ".$response->body());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function unsupported(Loan $loan, string $reason = ''): void
    {
        $provider = $loan->disbursement_method?->value ?? 'unknown';
        $msg = "Provider '{$provider}' not supported for MoMo disbursement".($reason ? ": {$reason}" : '');
        Log::warning("[MoMo:disburse] {$msg}", ['loan_id' => $loan->id]);
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    private function e164(string $phone): string
    {
        if (str_starts_with($phone, '0')) {
            return '+260'.substr($phone, 1);
        }

        return $phone;
    }
}
