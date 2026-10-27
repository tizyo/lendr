<?php

namespace App\Services\Payment;

use App\Enums\LoanStatus;
use App\Exceptions\PayoutRejected;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\Loan;
use App\Services\Payment\Concerns\ClassifiesPayouts;
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
    use ClassifiesPayouts;

    public function __construct(private readonly DisbursementOutcomeService $outcome) {}

    /**
     * Initiate a disbursement using the tenant wallet. The result is handed to
     * DisbursementOutcomeService (accepted / rejected → reversed / unconfirmed).
     */
    public function disburse(Loan $loan, TenantWallet $wallet): DisbursementLog
    {
        $phone = $loan->disbursement_account ?? $loan->borrower?->phone;

        // Only a loan activated by disburse() is awaiting a payout (not e.g. one just reversed).
        if ($loan->fresh()?->status !== LoanStatus::Active) {
            Log::warning('[AutoDisburse] Skipped — loan not awaiting a payout', ['loan_id' => $loan->id]);

            return $loan->disbursementLogs()->latest('id')->first() ?? new DisbursementLog(['loan_id' => $loan->id, 'status' => 'skipped']);
        }

        // Deterministic per loan (not time()-suffixed) so a retried job or a
        // duplicate dispatch resolves to the same reference. Combined with a
        // unique DB index on `reference`, this is what actually stops a
        // second real payout — the SELECT-then-INSERT below narrows the
        // window but can't close it alone under true concurrency.
        $reference = 'LENDR-DISB-'.$loan->id;

        $existing = DisbursementLog::where('reference', $reference)->first();

        // A rejected-and-reversed (or failed) attempt may be retried once staff re-disburse.
        if ($existing && ! in_array($existing->status, ['failed', 'reversed'], true)) {
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

        try {
            if (! $phone) {
                throw new PayoutRejected('No recipient phone found on loan or borrower.');
            }

            $providerRef = match ($wallet->gateway) {
                'flutterwave' => $this->flutterwave($loan, $wallet, $phone, $reference),
                'airtel_money' => $this->airtel($loan, $wallet, $phone, $reference),
                'mtn_momo' => $this->mtn($loan, $wallet, $phone, $reference),
                'zamtel_kwacha' => $this->zamtel($loan, $wallet, $phone, $reference),
                'pawapay' => $this->pawapay($loan, $wallet, $phone),
                default => throw new PayoutRejected("Unsupported gateway: {$wallet->gateway}"),
            };
        } catch (PayoutRejected $e) {
            $this->outcome->rejected($loan, $log, $e->getMessage());

            return $log->refresh();
        } catch (\Throwable $e) {
            $this->outcome->unconfirmed($loan, $log, $e->getMessage());

            return $log->refresh();
        }

        $this->outcome->accepted($loan, $log, $providerRef);

        return $log->refresh();
    }

    // ─── Flutterwave ─────────────────────────────────────────────────────────

    private function flutterwave(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->environment === 'production'
            ? 'https://api.flutterwave.com'
            : 'https://api.flutterwave.com'; // Flutterwave has no sandbox URL difference

        $e164Phone = $this->e164($phone);

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])->timeout(30)
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

        $this->assertFlutterwaveAccepted($response);

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

        $token = $this->beforeSending(function () use ($baseUrl, $clientId, $clientSecret) {
            $auth = Http::timeout(20)->post("{$baseUrl}/auth/oauth2/token", [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            return $auth->successful() && $auth->json('access_token')
                ? $auth->json('access_token')
                : throw new PayoutRejected('Airtel auth failed: '.Str::limit($auth->body(), 200));
        });

        $response = Http::withToken($token)->timeout(30)
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

        $this->assertAirtelAccepted($response);

        return (string) ($response->json('data.transaction.id') ?? $reference);
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

        $token = $this->beforeSending(function () use ($baseUrl, $subscriptionKey, $apiUser, $apiKey) {
            $auth = Http::timeout(20)
                ->withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
                ->withBasicAuth($apiUser, $apiKey)
                ->post("{$baseUrl}/disbursement/token/");

            return $auth->successful() && $auth->json('access_token')
                ? $auth->json('access_token')
                : throw new PayoutRejected('MTN token request failed: '.Str::limit($auth->body(), 200));
        });

        $requestUuid = (string) Str::uuid();

        $response = Http::withToken($token)->timeout(30)
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

        // MTN returns 202 Accepted for a queued transfer.
        if ($response->status() !== 202) {
            $this->providerError('MTN', $response);
        }

        return $requestUuid;
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(Loan $loan, TenantWallet $wallet, string $phone, string $reference): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->meta('zamtel_api_url') ?? 'https://api.zamtel.com';

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])->timeout(30)
            ->post(rtrim($baseUrl, '/').'/payments/disburse', [
                'reference' => $reference,
                'msisdn' => $this->e164($phone),
                'amount' => (float) $loan->principal_amount,
                'currency' => 'ZMW',
                'description' => "LENDR Loan {$loan->loan_number}",
            ]);

        if (! $response->successful()) {
            $this->providerError('Zamtel', $response);
        }

        return (string) ($response->json('reference') ?? $reference);
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(Loan $loan, TenantWallet $wallet, string $phone): string
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->environment === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

        // PawaPay requires a UUIDv4 payoutId (our LENDR-DISB-… reference isn't one).
        $payoutId = (string) Str::uuid();

        $response = Http::withToken($apiKey)->timeout(30)
            ->post("{$baseUrl}/payouts", [
                'payoutId' => $payoutId,
                'amount' => number_format((float) $loan->principal_amount, 2, '.', ''),
                'currency' => 'ZMW',
                'country' => 'ZMB',
                'correspondent' => $this->pawaPayCorrespondent($phone),
                'recipient' => ['type' => 'MSISDN', 'address' => ['value' => ltrim($this->e164($phone), '+')]],
                'customerTimestamp' => now()->toIso8601String(),
                'statementDescription' => Str::limit("Loan {$loan->loan_number}", 22, ''),
            ]);

        $this->assertPawaPayAccepted($response);

        return $payoutId;
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
