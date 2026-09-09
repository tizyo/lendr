<?php

namespace App\Services\Billing\Gateways;

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Contracts\BillingGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PawapayGateway implements BillingGatewayInterface
{
    public function __construct(private readonly BillingGatewayConfig $config) {}

    public function getName(): string
    {
        return 'pawapay';
    }

    public function initiatePayment(array $payload): string
    {
        $checkoutId = str_replace('LENDR-SUB-', '', $payload['tx_ref']);
        $returnUrl = $this->query($payload['redirect_url'], ['gateway' => 'pawapay', 'transaction_id' => $checkoutId, 'tx_ref' => $payload['tx_ref'], 'status' => 'success']);
        $data = Http::withToken($this->token())->post($this->baseUrl().'/v2/checkouts', [
            'checkoutId' => $checkoutId, 'returnUrl' => $returnUrl, 'returnMethod' => 'INSTANT', 'defaultLanguage' => 'en',
            'countries' => [$this->setting('country', 'ZMB')],
            'amounts' => [['country' => $this->setting('country', 'ZMB'), 'currency' => strtoupper($payload['currency']), 'amount' => number_format((float) $payload['amount'], 2, '.', '')]],
            'clientReferenceId' => $payload['tx_ref'],
            'reason' => ['en' => $this->reason($payload)],
            'metadata' => collect(['tx_ref' => $payload['tx_ref'], ...$payload['meta']])->map(fn ($value, $key) => [$key => (string) $value])->values()->all(),
        ])->throw()->json();
        $url = $data['redirectUrl'] ?? null;
        if (! is_string($url) || $url === '') {
            throw new RuntimeException('PawaPay did not return a checkout redirect URL.');
        }

        return $url;
    }

    public function verifyPayment(string $transactionId): array
    {
        if ($transactionId === '') {
            throw new RuntimeException('PawaPay checkout ID is missing.');
        }
        $response = Http::withToken($this->token())->get($this->baseUrl().'/v2/checkouts/'.rawurlencode($transactionId))->throw()->json();
        $data = $response['data'] ?? $response;
        $rawStatus = strtoupper($data['status'] ?? '');
        $deposit = $data['deposit'] ?? [];

        return [
            'status' => $rawStatus === 'COMPLETED' ? 'success' : (in_array($rawStatus, ['FAILED', 'EXPIRED', 'CANCELLED'], true) ? 'failed' : 'pending'),
            'amount' => (float) ($deposit['amount'] ?? data_get($data, 'amounts.0.amount', 0)),
            'currency' => $deposit['currency'] ?? data_get($data, 'amounts.0.currency', ''),
            'tx_ref' => $data['clientReferenceId'] ?? data_get($data, 'metadata.tx_ref', ''),
        ];
    }

    public function verifyWebhookSignature(Request $request): bool
    {
        $publicKey = trim((string) ($this->config->public_key ?? ''));
        $signature = $request->header('Signature', '');
        $input = $request->header('Signature-Input', '');
        if ($publicKey === '' || ! preg_match('/^[^=]+=:([^:]+):/', $signature, $sig) || ! preg_match('/^[^=]+=\(([^)]*)\)(.*)$/', $input, $params)) {
            return false;
        }

        if (! $this->validDigest($request) || ! $this->validSignatureTime($params[2])) {
            return false;
        }
        preg_match_all('/"([^"]+)"/', $params[1], $matches);
        $lines = [];
        foreach ($matches[1] as $component) {
            $value = match ($component) {
                '@method' => strtoupper($request->method()), '@authority' => $request->getHttpHost(), '@path' => '/'.$request->path(),
                default => $request->header($component),
            };
            if ($value === null) {
                return false;
            }
            $lines[] = '"'.$component.'": '.$value;
        }
        $lines[] = '"@signature-params": ('.$params[1].')'.$params[2];
        $algorithm = str_contains($params[2], 'rsa-pss-sha512') ? OPENSSL_ALGO_SHA512 : OPENSSL_ALGO_SHA256;

        return openssl_verify(implode("\n", $lines), base64_decode($sig[1], true) ?: '', $publicKey, $algorithm) === 1;
    }

    public function parseWebhookPayload(Request $request): array
    {
        $body = $request->json()->all();
        $data = $body['data'] ?? $body;
        $deposit = $data['deposit'] ?? [];
        $rawStatus = strtoupper($data['status'] ?? '');

        return [
            'event_id' => (string) ($data['checkoutId'] ?? $body['eventId'] ?? ''), 'event_type' => 'checkout.'.strtolower($rawStatus),
            'tx_ref' => $data['clientReferenceId'] ?? data_get($data, 'metadata.tx_ref', ''), 'transaction_id' => (string) ($data['checkoutId'] ?? ''),
            'amount' => (float) ($deposit['amount'] ?? 0),
            'currency' => (string) ($deposit['currency'] ?? ''),
            'status' => $rawStatus === 'COMPLETED' ? 'success' : (in_array($rawStatus, ['FAILED', 'EXPIRED', 'CANCELLED'], true) ? 'failed' : 'pending'), 'raw' => $body,
        ];
    }

    private function token(): string
    {
        $token = (string) ($this->config->secret_key ?? '');
        if ($token === '') {
            throw new RuntimeException('PawaPay API token is not configured.');
        }

        return $token;
    }

    private function baseUrl(): string
    {
        return rtrim($this->setting('base_url', 'https://api.sandbox.pawapay.io'), '/');
    }

    private function setting(string $key, string $default): string
    {
        return (string) data_get($this->config->extra_config, $key, $default);
    }

    private function query(string $url, array $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function reason(array $payload): string
    {
        return substr(preg_replace('/[^A-Za-z0-9 ]/', '', $payload['customizations']['title'] ?? 'LENDR Subscription'), 0, 22);
    }

    private function validDigest(Request $request): bool
    {
        $header = $request->header('Content-Digest', '');
        if (! preg_match('/^(sha-256|sha-512)=:([^:]+):$/i', $header, $match)) {
            return false;
        }

        $algorithm = strtolower($match[1]) === 'sha-512' ? 'sha512' : 'sha256';

        return hash_equals(base64_encode(hash($algorithm, $request->getContent(), true)), $match[2]);
    }

    private function validSignatureTime(string $parameters): bool
    {
        preg_match('/;created=(\d+)/', $parameters, $created);
        preg_match('/;expires=(\d+)/', $parameters, $expires);
        $now = time();

        return isset($created[1]) && abs($now - (int) $created[1]) <= 300
            && (! isset($expires[1]) || $now <= (int) $expires[1]);
    }
}
