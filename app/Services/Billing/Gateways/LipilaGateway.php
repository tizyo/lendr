<?php

namespace App\Services\Billing\Gateways;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LipilaGateway implements BillingGatewayInterface
{
    public function __construct(private readonly BillingGatewayConfig $config) {}

    public function getName(): string
    {
        return 'lipila';
    }

    public function initiatePayment(array $payload): string
    {
        $settings = $this->config->extra_config ?? [];
        foreach (['phone_number', 'city', 'country', 'address', 'zip', 'account_number'] as $key) {
            if (empty($settings[$key])) {
                throw new RuntimeException("Lipila setting [{$key}] is required for card checkout.");
            }
        }
        $names = preg_split('/\s+/', trim($payload['customer']['name']), 2);
        $returnUrl = $this->query($payload['redirect_url'], ['gateway' => 'lipila', 'transaction_id' => $payload['tx_ref'], 'tx_ref' => $payload['tx_ref'], 'status' => 'success']);
        $response = Http::withHeaders(['x-api-key' => $this->apiKey(), 'callbackUrl' => route('api.v1.webhooks.subscription', ['gateway' => 'lipila'])])
            ->post($this->baseUrl().'/api/v1/collections/card', [
                'customerInfo' => [
                    'firstName' => $names[0] ?: 'LENDR', 'lastName' => $names[1] ?? 'Customer',
                    'phoneNumber' => $settings['phone_number'], 'city' => $settings['city'], 'country' => $settings['country'],
                    'address' => $settings['address'], 'email' => $payload['customer']['email'], 'zip' => $settings['zip'],
                ],
                'collectionRequest' => [
                    'referenceId' => $payload['tx_ref'], 'amount' => (float) $payload['amount'],
                    'narration' => $payload['customizations']['description'] ?? 'LENDR subscription',
                    'accountNumber' => $settings['account_number'], 'currency' => strtoupper($payload['currency']),
                    'backUrl' => $returnUrl, 'referenceData' => $payload['tx_ref'],
                ],
            ])->throw();
        $url = $response->json('cardRedirectionUrl');
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Lipila did not return a card redirect URL.');
        }

        return $url;
    }

    public function verifyPayment(string $transactionId): array
    {
        if ($transactionId === '') {
            throw new RuntimeException('Lipila reference ID is missing.');
        }
        $data = Http::withHeaders(['x-api-key' => $this->apiKey()])->get($this->baseUrl().'/api/v1/collections/check-status', ['referenceId' => $transactionId])->throw()->json();
        $rawStatus = strtolower($data['status'] ?? '');

        return [
            'status' => in_array($rawStatus, ['successful', 'success', 'completed'], true) ? 'success' : (in_array($rawStatus, ['failed', 'cancelled'], true) ? 'failed' : 'pending'),
            'amount' => (float) ($data['amount'] ?? 0), 'currency' => $data['currency'] ?? '', 'tx_ref' => $data['referenceId'] ?? '',
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $secret = (string) ($this->config->webhook_secret ?? '');
        $id = $request->header('webhook-id', '');
        $timestamp = $request->header('webhook-timestamp', '');
        $signatures = $request->header('webhook-signature', '');
        if ($secret === '' || $id === '' || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > 300 || $signatures === '') {
            return false;
        }
        $key = base64_decode(str_starts_with($secret, 'whsec_') ? substr($secret, 6) : $secret, true);
        if ($key === false) {
            return false;
        }
        $expected = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$request->getContent(), $key, true));
        foreach (preg_split('/\s+/', trim($signatures)) as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $body = $request->json()->all();
        $data = $body['data'] ?? $body;
        $rawStatus = strtolower($data['status'] ?? '');

        return [
            'event_id' => $request->header('webhook-id', (string) ($data['identifier'] ?? '')), 'event_type' => (string) ($body['type'] ?? $body['event'] ?? 'collection.'.strtolower($rawStatus)),
            'tx_ref' => $data['referenceId'] ?? $data['reference'] ?? '', 'transaction_id' => (string) ($data['referenceId'] ?? $data['reference'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? ''),
            'status' => in_array($rawStatus, ['successful', 'success', 'completed'], true) ? 'success' : (in_array($rawStatus, ['failed', 'cancelled'], true) ? 'failed' : 'pending'), 'raw' => $body,
        ];
    }

    private function apiKey(): string
    {
        $key = (string) ($this->config->secret_key ?? '');
        if ($key === '') {
            throw new RuntimeException('Lipila API key is not configured.');
        }

        return $key;
    }

    private function baseUrl(): string
    {
        return rtrim((string) data_get($this->config->extra_config, 'base_url', 'https://api.lipila.dev'), '/');
    }

    private function query(string $url, array $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
