<?php

namespace App\Jobs;

use App\Exceptions\PayoutRejected;
use App\Models\Tenant\DisbursementLog;
use App\Models\Tenant\Loan;
use App\Services\Payment\Concerns\ClassifiesPayouts;
use App\Services\Payment\DisbursementOutcomeService;
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
 *
 * The outcome (accepted / rejected / unconfirmed) is handed to
 * DisbursementOutcomeService, which tells the borrower, or reverses the
 * disbursement, or asks staff to verify with the provider.
 */
class DisburseMobileMoneyJob implements ShouldQueue
{
    use ClassifiesPayouts, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Exactly one attempt: a blind retry after a timeout could pay the borrower
    // twice. Unknown outcomes go to staff instead (see DisbursementOutcomeService).
    public int $tries = 1;

    public function __construct(public readonly Loan $loan) {}

    public function handle(DisbursementOutcomeService $outcome): void
    {
        $loan = $this->loan->fresh(['borrower:id,phone,first_name']);

        if (! $loan || ! ($log = $this->openAttempt($loan))) {
            return;
        }

        try {
            $providerReference = $this->send($loan, $log);
        } catch (PayoutRejected $e) {
            $outcome->rejected($loan, $log, $e->getMessage());

            return;
        } catch (\Throwable $e) {
            $outcome->unconfirmed($loan, $log, $e->getMessage());

            return;
        }

        $outcome->accepted($loan, $log, $providerReference);
    }

    /**
     * Records this attempt, or returns null when the loan isn't awaiting a
     * payout or another attempt is already in flight (duplicate dispatch).
     */
    private function openAttempt(Loan $loan): ?DisbursementLog
    {
        return DB::transaction(function () use ($loan) {
            $locked = Loan::lockForUpdate()->find($loan->id);

            $inFlight = DisbursementLog::where('loan_id', $loan->id)
                ->whereIn('status', ['initiated', 'processing', 'unconfirmed', 'completed'])
                ->exists();

            if (! $locked || $locked->status->value !== 'active' || $inFlight) {
                Log::warning('[MoMo:disburse] Skipped — loan not awaiting a payout', ['loan_id' => $loan->id, 'in_flight' => $inFlight]);

                return null;
            }

            return DisbursementLog::create([
                'loan_id' => $loan->id,
                'gateway' => $loan->disbursement_method?->value ?? 'unknown',
                'reference' => 'LENDR-DISB-'.$loan->id.'-'.Str::lower(Str::random(8)),
                'amount' => $loan->principal_amount,
                'recipient_phone' => $loan->disbursement_account ?? $loan->borrower?->phone,
                'status' => 'initiated',
                'used_wallet' => false,
            ]);
        });
    }

    /** Returns the provider's reference for the accepted payout. */
    private function send(Loan $loan, DisbursementLog $log): string
    {
        $phone = $log->recipient_phone;

        if (! $phone) {
            throw new PayoutRejected('No mobile number on the loan or borrower.');
        }

        return match ($log->gateway) {
            'airtel_money' => $this->airtel($loan, $phone, $log->reference),
            'mtn_momo' => $this->mtn($loan, $phone, $log->reference),
            'zamtel_kwacha' => $this->zamtel($loan, $phone, $log->reference),
            'pawapay' => $this->pawapay($loan, $phone),
            default => throw new PayoutRejected("Provider '{$log->gateway}' is not supported for mobile money disbursement."),
        };
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(Loan $loan, string $phone): string
    {
        $apiKey = $this->requireSetting('pawapay_api_key', 'PawaPay');

        $env = $this->setting('pawapay_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

        // PawaPay requires a UUIDv4 payoutId.
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

    // ─── Airtel Money ─────────────────────────────────────────────────────────

    private function airtel(Loan $loan, string $phone, string $reference): string
    {
        $clientId = $this->requireSetting('airtel_client_id', 'Airtel');
        $clientSecret = $this->requireSetting('airtel_client_secret', 'Airtel');

        $env = $this->setting('airtel_environment', 'sandbox');
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
                'pin' => $this->setting('airtel_pin', ''),
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

    private function mtn(Loan $loan, string $phone, string $reference): string
    {
        // MTN disbursement product uses a separate subscription key
        $subscriptionKey = $this->setting('mtn_disbursement_subscription_key') ?? $this->setting('mtn_subscription_key');
        $apiUser = $this->setting('mtn_api_user');
        $apiKey = $this->setting('mtn_api_key');

        if (! $subscriptionKey || ! $apiUser || ! $apiKey) {
            throw new PayoutRejected('MTN credentials are not configured.');
        }

        $env = $this->setting('mtn_environment', 'sandbox');
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
                'payee' => ['partyIdType' => 'MSISDN', 'partyId' => ltrim($this->e164($phone), '+')],
                'payerMessage' => "LENDR Loan {$loan->loan_number} disbursement",
                'payeeNote' => $reference,
            ]);

        // MTN returns 202 Accepted for a queued transfer.
        if ($response->status() !== 202) {
            $this->providerError('MTN', $response);
        }

        return $requestUuid;
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(Loan $loan, string $phone, string $reference): string
    {
        $apiKey = $this->requireSetting('zamtel_api_key', 'Zamtel');
        $baseUrl = $this->requireSetting('zamtel_api_url', 'Zamtel');

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])->timeout(30)
            ->post(rtrim($baseUrl, '/').'/payments/disburse', [
                'reference' => $reference,
                'msisdn' => $this->e164($phone),
                'amount' => (float) $loan->principal_amount,
                'currency' => 'ZMW',
                'description' => "LENDR Loan {$loan->loan_number} disbursement",
            ]);

        if (! $response->successful()) {
            $this->providerError('Zamtel', $response);
        }

        return (string) ($response->json('reference') ?? $reference);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function requireSetting(string $key, string $provider): string
    {
        return (string) ($this->setting($key) ?: throw new PayoutRejected("{$provider} credentials are not configured ({$key})."));
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
