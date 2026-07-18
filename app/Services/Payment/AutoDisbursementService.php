<?php

namespace App\Services\Payment;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\Loan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes an auto-disbursement for an Enterprise tenant using the tenant's configured TenantWallet.
 *
 * Supported gateways:
 *   flutterwave  — POST /v3/transfers  (B2C, mobile money)
 *   airtel_money — OAuth2 + standard/v1/disbursements
 *   mtn_momo     — token + disbursement/v1_0/transfer
 *   zamtel_kwacha — Bearer + /payments/disburse
 *   pawapay      — Bearer + /payouts
 */
class AutoDisbursementService
{
    /**
     * Initiate a disbursement using the tenant wallet.
     * Returns the DisbursementLog record (status = initiated or processing or failed).
     */
    public function disburse(Loan $loan, TenantWallet $wallet): DisbursementLog
    {
        $phone = $loan->disbursement_account ?? $loan->borrower?->phone;

        // Deterministic per loan (not time()-suffixed) so a retried job or a
        // duplicate dispatch resolves to the same reference. Combined with a
        // unique DB index on `reference`, this is what actually stops a
        // second real payout — the SELECT-then-INSERT below narrows the
        // window but can't close it alone under true concurrency.
        $reference = 'LENDR-DISB-'.$loan->id;

        $existing = DisbursementLog::where('reference', $reference)->first();

        if ($existing && $existing->status !== 'failed') {
            Log::warning('[AutoDisburse] Duplicate disbursement attempt blocked', [
                'loan_id' => $loan->id,
                'existing_log_id' => $existing->id,
                'existing_status' => $existing->status,
            ]);

            return $existing;
        }

        if ($existing) {
            // Previous attempt failed — retry using the same reference/row.
            $log = tap($existing)->update([
                'gateway' => $wallet->gateway,
                'amount' => $loan->principal_amount,
                'recipient_phone' => $phone,
                'status' => 'initiated',
                'failure_reason' => null,
                'used_wallet' => true,
            ]);
        } else {
            try {
                $log = DisbursementLog::create([
                    'loan_id' => $loan->id,
                    'gateway' => $wallet->gateway,
                    'reference' => $reference,
                    'amount' => $loan->principal_amount,
                    'recipient_phone' => $phone,
                    'status' => 'initiated',
                    'used_wallet' => true,
                ]);
            } catch (QueryException $e) {
                // Lost the race to a concurrent attempt on the same loan —
                // the unique index rejected our insert. Return whatever the
                // winner created instead of disbursing a second time.
                $log = DisbursementLog::where('reference', $reference)->firstOrFail();

                Log::warning('[AutoDisburse] Concurrent disbursement attempt lost the race, using winner\'s log', [
                    'loan_id' => $loan->id,
                    'existing_log_id' => $log->id,
                ]);

                return $log;
            }
        }

        if (! $phone) {
            $log->update(['status' => 'failed', 'failure_reason' => 'No recipient phone found on loan or borrower.']);
            Log::warning('[AutoDisburse] No phone for loan', ['loan_id' => $loan->id]);

            return $log;
        }

        try {
            $providerRef = match ($wallet->gateway) {
                'flutterwave' => $this->flutterwave($loan, $wallet, $phone, $reference),
                'airtel_money' => $this->airtel($loan, $wallet, $phone, $reference),
                'mtn_momo' => $this->mtn($loan, $wallet, $phone, $reference),
                'zamtel_kwacha' => $this->zamtel($loan, $wallet, $phone, $reference),
                'pawapay' => $this->pawapay($loan, $wallet, $phone, $reference),
                default => throw new \RuntimeException("Unsupported gateway: {$wallet->gateway}"),
            };

            $log->update([
                'status' => 'processing',
                'provider_reference' => $providerRef,
            ]);

            Log::info('[AutoDisburse] Initiated', [
                'loan_id' => $loan->id,
                'gateway' => $wallet->gateway,
                'ref' => $reference,
                'provider' => $providerRef,
            ]);
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            Log::error('[AutoDisburse] Failed', [
                'loan_id' => $loan->id,
                'gateway' => $wallet->gateway,
                'error' => $e->getMessage(),
            ]);
        }

        return $log;
    }

    // ─── Flutterwave ─────────────────────────────────────────────────────────

    private function flutterwave(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->environment === 'production'
            ? 'https://api.flutterwave.com'
            : 'https://api.flutterwave.com'; // Flutterwave has no sandbox URL difference

        $e164Phone = $this->e164($phone);

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post("{$baseUrl}/v3/transfers", [
                'account_bank' => $this->flutterwaveBank($phone),
                'account_number' => $e164Phone,
                'amount' => (float) $loan->principal_amount,
                'narration' => "LENDR Loan {$loan->loan_number} disbursement",
                'currency' => $loan->currency ?? 'ZMW',
                'reference' => $reference,
                'callback_url' => url('/api/webhooks/flutterwave'),
                'beneficiary_name' => $loan->borrower?->first_name.' '.($loan->borrower?->last_name ?? ''),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Flutterwave transfer failed ({$response->status()}): ".$response->body());
        }

        return (string) ($response->json('data.id') ?? $reference);
    }

    private function flutterwaveBank(string $phone): string
    {
        $prefix = substr(ltrim($phone, '0+26'), 0, 2);

        return match (true) {
            in_array($prefix, ['96', '97', '95']) => 'AIRTEL',
            in_array($prefix, ['76', '77', '78']) => 'MTN',
            in_array($prefix, ['65', '66', '67']) => 'ZAMTEL',
            default => 'MTN',
        };
    }

    // ─── Airtel Money ─────────────────────────────────────────────────────────

    private function airtel(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $clientId = $wallet->api_key;
        $clientSecret = $wallet->api_secret;

        $env = $wallet->environment;
        $baseUrl = $env === 'production'
            ? 'https://openapi.airtel.africa'
            : 'https://openapiuat.airtel.africa';

        $authResponse = Http::post("{$baseUrl}/auth/oauth2/token", [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'client_credentials',
        ]);

        if (! $authResponse->successful()) {
            throw new \RuntimeException('Airtel auth failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');

        $response = Http::withToken($token)
            ->withHeaders(['X-Country' => 'ZM', 'X-Currency' => 'ZMW'])
            ->post("{$baseUrl}/standard/v1/disbursements/", [
                'payee' => ['msisdn' => ltrim($phone, '0')],
                'reference' => $reference,
                'pin' => $wallet->meta('airtel_pin', ''),
                'transaction' => [
                    'amount' => (float) $loan->principal_amount,
                    'id' => $reference,
                    'type' => 'B2C',
                ],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Airtel disbursement failed ({$response->status()}): ".$response->body());
        }

        return $response->json('data.transaction.id') ?? $reference;
    }

    // ─── MTN MoMo ─────────────────────────────────────────────────────────────

    private function mtn(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $subscriptionKey = $wallet->meta('mtn_disbursement_subscription_key') ?? $wallet->api_key;
        $apiUser = $wallet->meta('mtn_api_user') ?? $wallet->wallet_id;
        $apiKey = $wallet->api_secret ?? $wallet->api_key;

        $env = $wallet->environment;
        $baseUrl = $env === 'production'
            ? 'https://proxy.momoapi.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        $authResponse = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->withBasicAuth($apiUser, $apiKey)
            ->post("{$baseUrl}/disbursement/token/");

        if (! $authResponse->successful()) {
            throw new \RuntimeException('MTN token failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');
        $requestUuid = (string) Str::uuid();

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
                'payee' => ['partyIdType' => 'MSISDN', 'partyId' => $this->e164($phone)],
                'payerMessage' => "LENDR Loan {$loan->loan_number}",
                'payeeNote' => $reference,
            ]);

        if ($response->status() !== 202) {
            throw new \RuntimeException("MTN transfer failed ({$response->status()}): ".$response->body());
        }

        return $requestUuid;
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->meta('zamtel_api_url') ?? 'https://api.zamtel.com';

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post(rtrim($baseUrl, '/').'/payments/disburse', [
                'reference' => $reference,
                'msisdn' => $this->e164($phone),
                'amount' => (float) $loan->principal_amount,
                'currency' => 'ZMW',
                'description' => "LENDR Loan {$loan->loan_number}",
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Zamtel disbursement failed ({$response->status()}): ".$response->body());
        }

        return $response->json('reference') ?? $reference;
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->environment === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

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

        if (! $response->successful()) {
            throw new \RuntimeException("PawaPay payout failed ({$response->status()}): ".$response->body());
        }

        return $reference;
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

    private function e164(string $phone): string
    {
        return str_starts_with($phone, '0') ? '+260'.substr($phone, 1) : $phone;
    }
}
