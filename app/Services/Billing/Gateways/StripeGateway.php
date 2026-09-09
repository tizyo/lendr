<?php

namespace App\Services\Billing\Gateways;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class StripeGateway implements BillingGatewayInterface
{
    private const BASE = 'https://api.stripe.com/v1';

    public function __construct(private readonly BillingGatewayConfig $config) {}

    public function getName(): string
    {
        return 'stripe';
    }

    public function initiatePayment(array $payload): string
    {
        $txRef = $payload['tx_ref'];
        $response = Http::withToken($this->secretKey())->asForm()->post(self::BASE.'/checkout/sessions', [
            'mode' => 'payment',
            'success_url' => $this->query($payload['redirect_url'], ['gateway' => 'stripe', 'transaction_id' => '{CHECKOUT_SESSION_ID}', 'tx_ref' => $txRef, 'status' => 'success']),
            'cancel_url' => $this->query($payload['redirect_url'], ['gateway' => 'stripe', 'tx_ref' => $txRef, 'status' => 'cancelled']),
            'client_reference_id' => $txRef,
            'customer_email' => $payload['customer']['email'],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($payload['currency']),
                    'unit_amount' => $this->toMinorUnits((float) $payload['amount'], $payload['currency']),
                    'product_data' => ['name' => $payload['customizations']['title'] ?? 'LENDR Subscription', 'description' => $payload['customizations']['description'] ?? null],
                ],
                'quantity' => 1,
            ]],
            'metadata' => array_map('strval', ['tx_ref' => $txRef, ...$payload['meta']]),
            'payment_intent_data' => ['metadata' => array_map('strval', ['tx_ref' => $txRef, ...$payload['meta']])],
        ])->throw();

        $url = $response->json('url');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Stripe did not return a Checkout URL.');
        }

        return $url;
    }

    public function verifyPayment(string $transactionId): array
    {
        if ($transactionId === '') {
            throw new RuntimeException('Stripe Checkout Session ID is missing.');
        }
        $data = Http::withToken($this->secretKey())->get(self::BASE.'/checkout/sessions/'.rawurlencode($transactionId))->throw()->json();

        return [
            'status' => ($data['payment_status'] ?? '') === 'paid' ? 'success' : (($data['status'] ?? '') === 'expired' ? 'failed' : 'pending'),
            'amount' => ((int) ($data['amount_total'] ?? 0)) / $this->factor($data['currency'] ?? 'usd'),
            'currency' => strtoupper($data['currency'] ?? ''),
            'tx_ref' => $data['client_reference_id'] ?? data_get($data, 'metadata.tx_ref', ''),
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = $this->config->webhook_secret ?: null;
        $header = $request->header('Stripe-Signature', '');
        if (! $secret || ! $header) {
            Log::warning('[BillingWebhook:stripe] Missing signing secret or signature');

            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);
            if ($key && $value) {
                $parts[$key][] = $value;
            }
        }
        $timestamp = isset($parts['t'][0]) ? (int) $parts['t'][0] : 0;
        if ($timestamp <= 0 || abs(time() - $timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp.'.'.$request->getContent(), $secret);
        foreach ($parts['v1'] ?? [] as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $body = $request->json()->all();
        $object = data_get($body, 'data.object', []);

        return [
            'event_id' => (string) ($body['id'] ?? ''), 'event_type' => (string) ($body['type'] ?? ''),
            'tx_ref' => $object['client_reference_id'] ?? data_get($object, 'metadata.tx_ref', ''),
            'transaction_id' => (string) ($object['id'] ?? ''),
            'amount' => ((int) ($object['amount_total'] ?? 0)) / $this->factor($object['currency'] ?? 'usd'),
            'currency' => strtoupper($object['currency'] ?? ''),
            'status' => ($object['payment_status'] ?? '') === 'paid' ? 'success' : 'pending', 'raw' => $body,
        ];
    }

    private function secretKey(): string
    {
        $key = (string) ($this->config->secret_key ?? '');
        if ($key === '') {
            throw new RuntimeException('Stripe secret key is not configured.');
        }

        return $key;
    }

    private function query(string $url, array $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function toMinorUnits(float $amount, string $currency): int
    {
        return (int) round($amount * $this->factor($currency));
    }

    private function factor(string $currency): int
    {
        return in_array(strtoupper($currency), ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true) ? 1 : 100;
    }
}
