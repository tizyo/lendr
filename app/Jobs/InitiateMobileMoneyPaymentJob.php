<?php

namespace App\Jobs;

use App\Models\Tenant\MobileMoneyIntent;
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
 * Calls the provider's push-pay API to prompt the borrower to approve a payment.
 * Supported providers: pawapay, airtel_money, mtn_momo, zamtel_kwacha, lipila, flutterwave.
 *
 * Settings keys consumed (from tenant settings table):
 *   pawapay:      pawapay_api_key, pawapay_environment (sandbox|production)
 *   airtel_money: airtel_client_id, airtel_client_secret, airtel_environment (sandbox|production)
 *   mtn_momo:     mtn_subscription_key, mtn_api_user, mtn_api_key, mtn_environment (sandbox|production)
 *   zamtel_kwacha:zamtel_api_key, zamtel_api_url
 *   lipila:       lipila_api_key, lipila_api_url
 *   flutterwave:  flutterwave_secret_key (redirect-based; push-pay not supported — skipped)
 */
class InitiateMobileMoneyPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 15;

    public function __construct(public readonly MobileMoneyIntent $intent) {}

    public function handle(): void
    {
        $intent = $this->intent;

        if ($intent->status !== 'pending') {
            return; // Already processed or expired
        }

        try {
            $this->dispatch($intent);
        } catch (\Throwable $e) {
            Log::error('[MoMo] Push-pay initiation failed', [
                'ref' => $intent->reference,
                'provider' => $intent->provider,
                'error' => $e->getMessage(),
            ]);
            $intent->update(['status' => 'failed']);
            $this->fail($e);
        }
    }

    private function dispatch(MobileMoneyIntent $intent): void
    {
        match ($intent->provider) {
            'pawapay' => $this->pawapay($intent),
            'airtel_money' => $this->airtel($intent),
            'mtn_momo' => $this->mtn($intent),
            'zamtel_kwacha' => $this->zamtel($intent),
            'lipila' => $this->lipila($intent),
            default => $this->unsupported($intent),
        };
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(MobileMoneyIntent $intent): void
    {
        $apiKey = $this->setting('pawapay_api_key');
        if (! $apiKey) {
            $this->unsupported($intent, 'pawapay_api_key not configured');

            return;
        }

        $env = $this->setting('pawapay_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/deposits", [
                'depositId' => $intent->reference,
                'returnUrl' => null,
                'amount' => number_format((float) $intent->amount, 2, '.', ''),
                'currency' => 'ZMW',
                'country' => 'ZMB',
                'correspondent' => $this->pawaPayCorrespondent($intent->phone),
                'payer' => ['type' => 'MSISDN', 'address' => ['value' => $this->e164($intent->phone)]],
                'customerTimestamp' => now()->toIso8601String(),
                'statementDescription' => $intent->reference,
            ]);

        $this->handleResponse($intent, $response, 'pawapay', 'ACCEPTED');
    }

    private function pawaPayCorrespondent(string $phone): string
    {
        $prefix = substr(ltrim($phone, '0'), 0, 2); // e.g. 97, 76

        return match (true) {
            in_array($prefix, ['96', '97', '95']) => 'AIRTEL_ZAMBIA',
            in_array($prefix, ['76', '77', '78']) => 'MTN_ZAMBIA',
            in_array($prefix, ['65', '66', '67']) => 'ZAMTEL',
            default => 'AIRTEL_ZAMBIA',
        };
    }

    // ─── Airtel Money ─────────────────────────────────────────────────────────

    private function airtel(MobileMoneyIntent $intent): void
    {
        $clientId = $this->setting('airtel_client_id');
        $clientSecret = $this->setting('airtel_client_secret');
        if (! $clientId || ! $clientSecret) {
            $this->unsupported($intent, 'Airtel credentials not configured');

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

        // Step 2 — request collection
        $response = Http::withToken($token)
            ->withHeaders([
                'X-Country' => 'ZM',
                'X-Currency' => 'ZMW',
            ])
            ->post("{$baseUrl}/merchant/v2/payments/", [
                'reference' => $intent->reference,
                'subscriber' => [
                    'country' => 'ZM',
                    'currency' => 'ZMW',
                    'msisdn' => ltrim($intent->phone, '0'),
                ],
                'transaction' => [
                    'amount' => (float) $intent->amount,
                    'country' => 'ZM',
                    'currency' => 'ZMW',
                    'id' => $intent->reference,
                ],
            ]);

        $this->handleResponse($intent, $response, 'airtel', 'SUCCESS');
    }

    // ─── MTN MoMo ─────────────────────────────────────────────────────────────

    private function mtn(MobileMoneyIntent $intent): void
    {
        $subscriptionKey = $this->setting('mtn_subscription_key');
        $apiUser = $this->setting('mtn_api_user');
        $apiKey = $this->setting('mtn_api_key');
        if (! $subscriptionKey || ! $apiUser || ! $apiKey) {
            $this->unsupported($intent, 'MTN credentials not configured');

            return;
        }

        $env = $this->setting('mtn_environment', 'sandbox');
        $baseUrl = $env === 'production'
            ? 'https://proxy.momoapi.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        // Step 1 — obtain Bearer token
        $authResponse = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->withBasicAuth($apiUser, $apiKey)
            ->post("{$baseUrl}/collection/token/");

        if (! $authResponse->successful()) {
            throw new \RuntimeException('MTN token fetch failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');
        $requestUuid = (string) Str::uuid();

        // Step 2 — request to pay
        $response = Http::withToken($token)
            ->withHeaders([
                'X-Reference-Id' => $requestUuid,
                'X-Target-Environment' => $env,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])
            ->post("{$baseUrl}/collection/v1_0/requesttopay", [
                'amount' => (string) (float) $intent->amount,
                'currency' => 'ZMW',
                'externalId' => $intent->reference,
                'payer' => [
                    'partyIdType' => 'MSISDN',
                    'partyId' => $this->e164($intent->phone),
                ],
                'payerMessage' => 'LENDR loan repayment',
                'payeeNote' => $intent->reference,
            ]);

        // MTN returns 202 Accepted for a queued request
        if ($response->status() === 202) {
            $intent->update(['status' => 'processing']);
            Log::info('[MoMo:mtn] Request-to-pay accepted', ['ref' => $intent->reference, 'uuid' => $requestUuid]);

            return;
        }

        throw new \RuntimeException("MTN request-to-pay failed ({$response->status()}): ".$response->body());
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(MobileMoneyIntent $intent): void
    {
        $apiKey = $this->setting('zamtel_api_key');
        $baseUrl = $this->setting('zamtel_api_url');
        if (! $apiKey || ! $baseUrl) {
            $this->unsupported($intent, 'Zamtel credentials not configured');

            return;
        }

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post(rtrim($baseUrl, '/').'/payments/collect', [
                'reference' => $intent->reference,
                'msisdn' => $this->e164($intent->phone),
                'amount' => (float) $intent->amount,
                'currency' => 'ZMW',
                'description' => 'LENDR loan repayment',
            ]);

        $this->handleResponse($intent, $response, 'zamtel', '00');
    }

    // ─── Lipila ──────────────────────────────────────────────────────────────

    private function lipila(MobileMoneyIntent $intent): void
    {
        $apiKey = $this->setting('lipila_api_key');
        $baseUrl = $this->setting('lipila_api_url');
        if (! $apiKey || ! $baseUrl) {
            $this->unsupported($intent, 'Lipila credentials not configured');

            return;
        }

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post(rtrim($baseUrl, '/').'/request', [
                'reference' => $intent->reference,
                'phone' => $this->e164($intent->phone),
                'amount' => (float) $intent->amount,
                'currency' => 'ZMW',
                'order_ref' => $intent->reference,
                'narration' => 'LENDR loan repayment',
            ]);

        $this->handleResponse($intent, $response, 'lipila', '200');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function handleResponse(MobileMoneyIntent $intent, \Illuminate\Http\Client\Response $response, string $provider, string $successCode): void
    {
        if ($response->successful()) {
            $intent->update(['status' => 'processing']);
            Log::info("[MoMo:{$provider}] Push-pay accepted", ['ref' => $intent->reference]);

            return;
        }

        throw new \RuntimeException("{$provider} push-pay failed ({$response->status()}): ".$response->body());
    }

    private function unsupported(MobileMoneyIntent $intent, string $reason = ''): void
    {
        $msg = "Provider '{$intent->provider}' does not support push-pay".($reason ? ": {$reason}" : '');
        Log::warning("[MoMo] {$msg}", ['ref' => $intent->reference]);
        // Leave intent in 'pending' — borrower will need to pay manually
    }

    private function setting(string $key, mixed $default = null): mixed
    {
        return DB::table('settings')->where('key', $key)->value('value') ?? $default;
    }

    private function e164(string $phone): string
    {
        // 0971234567 → +260971234567
        if (str_starts_with($phone, '0')) {
            return '+260'.substr($phone, 1);
        }

        return $phone;
    }
}
