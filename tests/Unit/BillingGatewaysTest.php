<?php

use App\Models\Landlord\BillingGatewayConfig;
use App\Services\Billing\Gateways\LipilaGateway;
use App\Services\Billing\Gateways\PawapayGateway;
use App\Services\Billing\Gateways\StripeGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

function billingPayload(): array
{
    return [
        'tx_ref' => 'LENDR-SUB-550e8400-e29b-41d4-a716-446655440000', 'amount' => 1499.00, 'currency' => 'ZMW',
        'redirect_url' => 'https://lendr.test/billing/callback',
        'customer' => ['email' => 'owner@example.com', 'name' => 'Jane Doe'],
        'meta' => ['tenant_id' => 'tenant-1', 'plan' => 'growth', 'invoice_id' => 1],
        'customizations' => ['title' => 'LENDR Subscription', 'description' => 'Growth Plan'],
    ];
}

function gatewayConfig(string $gateway, array $extra = []): BillingGatewayConfig
{
    return new BillingGatewayConfig([
        'gateway' => $gateway, 'secret_key' => 'secret-key', 'webhook_secret' => base64_encode(str_repeat('x', 32)), 'extra_config' => $extra,
    ]);
}

it('creates and verifies a Stripe Checkout Session', function () {
    Http::fake([
        'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_test_1', 'url' => 'https://checkout.stripe.com/c/pay/test'], 200),
        'api.stripe.com/v1/checkout/sessions/cs_test_1' => Http::response(['id' => 'cs_test_1', 'payment_status' => 'paid', 'amount_total' => 149900, 'currency' => 'zmw', 'client_reference_id' => billingPayload()['tx_ref']], 200),
    ]);
    $gateway = new StripeGateway(gatewayConfig('stripe'));
    expect($gateway->initiatePayment(billingPayload()))->toStartWith('https://checkout.stripe.com/');
    expect($gateway->verifyPayment('cs_test_1'))->toMatchArray(['status' => 'success', 'amount' => 1499.0, 'currency' => 'ZMW', 'tx_ref' => billingPayload()['tx_ref']]);
    Http::assertSent(fn ($request) => str_contains($request->url(), '/checkout/sessions') && str_contains((string) $request->body(), 'unit_amount'));
});

it('validates Stripe webhook signatures and rejects stale signatures', function () {
    $config = gatewayConfig('stripe');
    $config->webhook_secret = 'whsec_test';
    $gateway = new StripeGateway($config);
    $body = json_encode(['id' => 'evt_1']);
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test');
    $request = Request::create('/webhook', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}"], $body);
    expect($gateway->verifyWebhookSignature($request))->toBeTrue();
    $stale = $timestamp - 301;
    $staleSignature = hash_hmac('sha256', $stale.'.'.$body, 'whsec_test');
    $request = Request::create('/webhook', 'POST', [], [], [], ['HTTP_STRIPE_SIGNATURE' => "t={$stale},v1={$staleSignature}"], $body);
    expect($gateway->verifyWebhookSignature($request))->toBeFalse();
});

it('creates and verifies a PawaPay v2 checkout', function () {
    Http::fake([
        'api.sandbox.pawapay.io/v2/checkouts' => Http::response(['redirectUrl' => 'https://checkout.sandbox.pawapay.io/code'], 200),
        'api.sandbox.pawapay.io/v2/checkouts/*' => Http::response(['status' => 'FOUND', 'data' => ['status' => 'COMPLETED', 'clientReferenceId' => billingPayload()['tx_ref'], 'deposit' => ['amount' => '1499.00', 'currency' => 'ZMW']]], 200),
    ]);
    $gateway = new PawapayGateway(gatewayConfig('pawapay'));
    expect($gateway->initiatePayment(billingPayload()))->toStartWith('https://checkout.sandbox.pawapay.io/');
    expect($gateway->verifyPayment('550e8400-e29b-41d4-a716-446655440000'))->toMatchArray(['status' => 'success', 'amount' => 1499.0, 'currency' => 'ZMW']);
});

it('creates and verifies a Lipila card collection', function () {
    $extra = ['phone_number' => '260971234567', 'city' => 'Lusaka', 'country' => 'ZM', 'address' => 'Cairo Road', 'zip' => '10101', 'account_number' => '260971234567'];
    Http::fake([
        'api.lipila.dev/api/v1/collections/card' => Http::response(['cardRedirectionUrl' => 'https://checkout.primenetpay.com/payment'], 200),
        'api.lipila.dev/api/v1/collections/check-status*' => Http::response(['referenceId' => billingPayload()['tx_ref'], 'status' => 'Successful', 'amount' => 1499, 'currency' => 'ZMW'], 200),
    ]);
    $gateway = new LipilaGateway(gatewayConfig('lipila', $extra));
    expect($gateway->initiatePayment(billingPayload()))->toStartWith('https://checkout.primenetpay.com/');
    expect($gateway->verifyPayment(billingPayload()['tx_ref']))->toMatchArray(['status' => 'success', 'amount' => 1499.0, 'currency' => 'ZMW']);
});

it('validates Lipila standard webhook signatures', function () {
    $config = gatewayConfig('lipila');
    $gateway = new LipilaGateway($config);
    $body = json_encode(['data' => ['status' => 'Successful']]);
    $id = 'event-1';
    $timestamp = (string) time();
    $key = str_repeat('x', 32);
    $signature = 'v1,'.base64_encode(hash_hmac('sha256', $id.'.'.$timestamp.'.'.$body, $key, true));
    $request = Request::create('/webhook', 'POST', [], [], [], ['HTTP_WEBHOOK_ID' => $id, 'HTTP_WEBHOOK_TIMESTAMP' => $timestamp, 'HTTP_WEBHOOK_SIGNATURE' => $signature], $body);
    expect($gateway->verifyWebhookSignature($request))->toBeTrue();
});
