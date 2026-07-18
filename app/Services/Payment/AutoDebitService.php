<?php

namespace App\Services\Payment;

use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StandingOrder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Executes an auto-debit collection for a standing order using the tenant's TenantWallet.
 *
 * Supported gateways:
 *   flutterwave  — POST /v3/charges?type=mobile_money_zambia
 *   airtel_money — OAuth2 + standard/v1/payments/mobile
 *   mtn_momo     — token + collection/v1_0/requesttopay
 *   zamtel_kwacha — Bearer + /payments/collect
 *   pawapay      — Bearer + /deposits
 *
 * On success: marks order processing; payment is recorded when webhook confirms.
 * On failure: calls StandingOrder::recordFailure() which handles retry scheduling.
 */
class AutoDebitService
{
    public function __construct(private PaymentService $payments) {}

    /**
     * Initiate a collection request for the given standing order.
     */
    public function collect(StandingOrder $order, TenantWallet $wallet): void
    {
        $reference = 'LENDR-DEBIT-'.$order->id.'-'.time();

        $order->update([
            'status' => 'processing',
            'provider_reference' => $reference,
        ]);

        try {
            match ($wallet->gateway) {
                'flutterwave' => $this->flutterwave($order, $wallet, $reference),
                'airtel_money' => $this->airtel($order, $wallet, $reference),
                'mtn_momo' => $this->mtn($order, $wallet, $reference),
                'zamtel_kwacha' => $this->zamtel($order, $wallet, $reference),
                'pawapay' => $this->pawapay($order, $wallet, $reference),
                default => throw new \RuntimeException("Unsupported gateway: {$wallet->gateway}"),
            };

            Log::info('[AutoDebit] Collection initiated', [
                'order_id' => $order->id,
                'gateway' => $wallet->gateway,
                'ref' => $reference,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[AutoDebit] Collection failed', [
                'order_id' => $order->id,
                'gateway' => $wallet->gateway,
                'error' => $e->getMessage(),
            ]);

            $order->recordFailure($e->getMessage());
        }
    }

    /**
     * Called by the webhook handler when a LENDR-DEBIT-* reference is confirmed.
     * Records the payment and marks the standing order completed.
     */
    public function confirmFromWebhook(StandingOrder $order, array $payload): void
    {
        if ($order->status === 'completed') {
            return; // idempotent
        }

        $payment = $this->payments->record($order->loan, [
            'amount' => (float) $order->amount,
            'payment_method' => $order->gateway,
            'payment_date' => now()->toDateString(),
            'source' => 'mobile_money_webhook',
            'momo_transaction_id' => $payload['transaction_id'] ?? null,
            'momo_provider' => $order->gateway,
            'reference' => $order->provider_reference,
            'notes' => 'Auto-debit standing order #'.$order->id,
        ]);

        $order->update([
            'status' => 'completed',
            'payment_id' => $payment->id,
            'processed_at' => now(),
        ]);

        Log::info('[AutoDebit] Standing order completed', [
            'order_id' => $order->id,
            'payment_id' => $payment->id,
        ]);
    }

    // ─── Flutterwave ─────────────────────────────────────────────────────────

    private function flutterwave(StandingOrder $order, TenantWallet $wallet, string $reference): void
    {
        $apiKey = $wallet->api_key;
        $baseUrl = 'https://api.flutterwave.com';

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post("{$baseUrl}/v3/charges?type=mobile_money_zambia", [
                'phone_number' => $this->e164($order->phone),
                'amount' => (float) $order->amount,
                'currency' => 'ZMW',
                'email' => $order->borrower?->email ?? 'noreply@lendr.app',
                'tx_ref' => $reference,
                'fullname' => $order->borrower?->first_name.' '.($order->borrower?->last_name ?? ''),
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Flutterwave charge failed ({$response->status()}): ".$response->body());
        }
    }

    // ─── Airtel Money ─────────────────────────────────────────────────────────

    private function airtel(StandingOrder $order, TenantWallet $wallet, string $reference): void
    {
        $env = $wallet->environment;
        $baseUrl = $env === 'production'
            ? 'https://openapi.airtel.africa'
            : 'https://openapiuat.airtel.africa';

        $authResponse = Http::post("{$baseUrl}/auth/oauth2/token", [
            'client_id' => $wallet->api_key,
            'client_secret' => $wallet->api_secret,
            'grant_type' => 'client_credentials',
        ]);

        if (! $authResponse->successful()) {
            throw new \RuntimeException('Airtel auth failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');
        $response = Http::withToken($token)
            ->withHeaders(['X-Country' => 'ZM', 'X-Currency' => 'ZMW'])
            ->post("{$baseUrl}/merchant/v1/payments/", [
                'reference' => $reference,
                'subscriber' => ['country' => 'ZM', 'currency' => 'ZMW', 'msisdn' => ltrim($order->phone, '0')],
                'transaction' => ['amount' => (float) $order->amount, 'country' => 'ZM', 'currency' => 'ZMW', 'id' => $reference],
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Airtel collect failed ({$response->status()}): ".$response->body());
        }
    }

    // ─── MTN MoMo ─────────────────────────────────────────────────────────────

    private function mtn(StandingOrder $order, TenantWallet $wallet, string $reference): void
    {
        $subscriptionKey = $wallet->meta('mtn_collection_subscription_key') ?? $wallet->api_key;
        $apiUser = $wallet->meta('mtn_api_user') ?? $wallet->wallet_id;
        $apiKey = $wallet->api_secret ?? $wallet->api_key;

        $env = $wallet->environment;
        $baseUrl = $env === 'production'
            ? 'https://proxy.momoapi.mtn.com'
            : 'https://sandbox.momodeveloper.mtn.com';

        $authResponse = Http::withHeaders(['Ocp-Apim-Subscription-Key' => $subscriptionKey])
            ->withBasicAuth($apiUser, $apiKey)
            ->post("{$baseUrl}/collection/token/");

        if (! $authResponse->successful()) {
            throw new \RuntimeException('MTN collection token failed: '.$authResponse->body());
        }

        $token = $authResponse->json('access_token');
        $requestUuid = (string) Str::uuid();

        $response = Http::withToken($token)
            ->withHeaders([
                'X-Reference-Id' => $requestUuid,
                'X-Target-Environment' => $env,
                'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            ])
            ->post("{$baseUrl}/collection/v1_0/requesttopay", [
                'amount' => (string) (float) $order->amount,
                'currency' => 'ZMW',
                'externalId' => $reference,
                'payer' => ['partyIdType' => 'MSISDN', 'partyId' => $this->e164($order->phone)],
                'payerMessage' => 'Loan repayment - LENDR',
                'payeeNote' => $reference,
            ]);

        if ($response->status() !== 202) {
            throw new \RuntimeException("MTN requesttopay failed ({$response->status()}): ".$response->body());
        }
    }

    // ─── Zamtel Kwacha ────────────────────────────────────────────────────────

    private function zamtel(StandingOrder $order, TenantWallet $wallet, string $reference): void
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->meta('zamtel_api_url') ?? 'https://api.zamtel.com';

        $response = Http::withHeaders(['Authorization' => "Bearer {$apiKey}"])
            ->post(rtrim($baseUrl, '/').'/payments/collect', [
                'reference' => $reference,
                'msisdn' => $this->e164($order->phone),
                'amount' => (float) $order->amount,
                'currency' => 'ZMW',
                'description' => 'Loan repayment - LENDR',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("Zamtel collect failed ({$response->status()}): ".$response->body());
        }
    }

    // ─── PawaPay ─────────────────────────────────────────────────────────────

    private function pawapay(StandingOrder $order, TenantWallet $wallet, string $reference): void
    {
        $apiKey = $wallet->api_key;
        $baseUrl = $wallet->environment === 'production'
            ? 'https://api.pawapay.io'
            : 'https://api.sandbox.pawapay.cloud';

        $prefix = substr(ltrim($order->phone, '0'), 0, 2);
        $correspondent = match (true) {
            in_array($prefix, ['96', '97', '95']) => 'AIRTEL_ZAMBIA',
            in_array($prefix, ['76', '77', '78']) => 'MTN_ZAMBIA',
            in_array($prefix, ['65', '66', '67']) => 'ZAMTEL',
            default => 'AIRTEL_ZAMBIA',
        };

        $response = Http::withToken($apiKey)
            ->post("{$baseUrl}/deposits", [
                'depositId' => $reference,
                'amount' => number_format((float) $order->amount, 2, '.', ''),
                'currency' => 'ZMW',
                'country' => 'ZMB',
                'correspondent' => $correspondent,
                'payer' => ['type' => 'MSISDN', 'address' => ['value' => $this->e164($order->phone)]],
                'customerTimestamp' => now()->toIso8601String(),
                'statementDescription' => 'Loan repayment - LENDR',
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException("PawaPay deposit failed ({$response->status()}): ".$response->body());
        }
    }

    private function e164(string $phone): string
    {
        return str_starts_with($phone, '0') ? '+260'.substr($phone, 1) : $phone;
    }
}
