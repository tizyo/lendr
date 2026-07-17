<?php

use App\Models\Landlord\BillingGatewayConfig;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;
use App\Services\Billing\BillingGatewayManager;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\Http;

// ─── Cleanup ──────────────────────────────────────────────────────────────────
afterEach(function () {
    // Billing tests use central DB only — no tenant DB context to clean up.
    SubscriptionInvoice::query()->delete();
    Subscription::query()->delete();
    BillingGatewayConfig::query()->delete();
    Tenant::query()->delete();
});

// ─── Helpers ──────────────────────────────────────────────────────────────────
function createActiveTenant(string $plan = 'starter', string $status = 'active'): Tenant
{
    // Billing tests only operate on the central DB — no tenant DB needed.
    return Tenant::create([
        'id'       => (string) \Illuminate\Support\Str::uuid(),
        'name'     => 'Test MFI',
        'slug'     => 'test-mfi-' . uniqid(),
        'plan'     => $plan,
        'status'   => $status,
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]);
}

function createGatewayConfig(bool $active = true): BillingGatewayConfig
{
    return BillingGatewayConfig::create([
        'gateway'        => 'flutterwave',
        'is_active'      => $active,
        'public_key'     => 'FLWPUBK_TEST-test',
        'secret_key'     => 'FLWSECK_TEST-test',
        'webhook_secret' => 'test-webhook-secret',
    ]);
}

// ─── BillingGatewayManager ────────────────────────────────────────────────────

it('resolves the active gateway', function () {
    createGatewayConfig(active: true);

    $manager = new BillingGatewayManager();
    $gateway = $manager->active();

    expect($gateway->getName())->toBe('flutterwave');
});

it('throws when no active gateway is configured', function () {
    expect(fn () => (new BillingGatewayManager())->active())
        ->toThrow(\RuntimeException::class, 'No active billing gateway');
});

it('resolves a gateway by name for webhook processing', function () {
    createGatewayConfig(active: false);

    $gateway = (new BillingGatewayManager())->driver('flutterwave');
    expect($gateway->getName())->toBe('flutterwave');
});

// ─── BillingService::initiateCheckout ────────────────────────────────────────

it('creates a pending invoice and returns the gateway URL', function () {
    Http::fake([
        'api.flutterwave.com/v3/payments' => Http::response([
            'status' => 'success',
            'data'   => ['link' => 'https://checkout.flutterwave.com/v3/hosted/pay/test123'],
        ], 200),
    ]);

    createGatewayConfig();
    $tenant  = createActiveTenant();
    $service = new BillingService(new BillingGatewayManager());

    $url = $service->initiateCheckout($tenant, 'growth', 'monthly');

    expect($url)->toBe('https://checkout.flutterwave.com/v3/hosted/pay/test123');

    $invoice = SubscriptionInvoice::where('tenant_id', $tenant->id)->first();
    expect($invoice)->not->toBeNull();
    expect($invoice->status)->toBe('pending');
    expect($invoice->plan)->toBe('growth');
    expect($invoice->gateway)->toBe('flutterwave');
    expect($invoice->gateway_tx_ref)->toStartWith('LENDR-SUB-');
});

// ─── BillingService::handleCallback ──────────────────────────────────────────

it('activates tenant on successful callback', function () {
    Http::fake([
        'api.flutterwave.com/v3/transactions/*/verify' => Http::response([
            'status' => 'success',
            'data'   => ['status' => 'successful', 'amount' => 1499, 'currency' => 'ZMW', 'tx_ref' => 'LENDR-SUB-test'],
        ], 200),
    ]);

    createGatewayConfig();
    $tenant = createActiveTenant(plan: 'starter', status: 'trial');

    $invoice = SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-test',
        'plan'           => 'growth',
        'amount'         => 1499,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'pending',
    ]);

    $service = new BillingService(new BillingGatewayManager());
    $result  = $service->handleCallback('txid-123', 'LENDR-SUB-test', 'successful');

    expect($result['success'])->toBeTrue();
    expect($result['plan'])->toBe('growth');

    $tenant->refresh();
    expect($tenant->plan)->toBe('growth');
    expect($tenant->status)->toBe('active');
    expect($tenant->trial_ends_at)->toBeNull();

    $invoice->refresh();
    expect($invoice->status)->toBe('paid');
    expect($invoice->gateway_tx_id)->toBe('txid-123');
    expect($invoice->paid_at)->not->toBeNull();

    $subscription = Subscription::where('tenant_id', $tenant->id)->where('status', 'active')->first();
    expect($subscription)->not->toBeNull();
    expect($subscription->plan)->toBe('growth');
    expect($subscription->ends_at)->not->toBeNull();
});

it('marks invoice failed on non-success callback status', function () {
    createGatewayConfig();
    $tenant = createActiveTenant(status: 'trial');

    $invoice = SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-failed',
        'plan'           => 'growth',
        'amount'         => 1499,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'pending',
    ]);

    $service = new BillingService(new BillingGatewayManager());
    $result  = $service->handleCallback('', 'LENDR-SUB-failed', 'cancelled');

    expect($result['success'])->toBeFalse();
    $invoice->refresh();
    expect($invoice->status)->toBe('failed');
});

it('returns success immediately if invoice already paid (idempotent)', function () {
    Http::fake(); // enable recording so we can assert nothing was sent

    createGatewayConfig();
    $tenant = createActiveTenant();

    SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-already-paid',
        'plan'           => 'growth',
        'amount'         => 1499,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'paid',
        'paid_at'        => now(),
    ]);

    $service = new BillingService(new BillingGatewayManager());
    $result  = $service->handleCallback('txid-999', 'LENDR-SUB-already-paid', 'successful');

    expect($result['success'])->toBeTrue();
    Http::assertNothingSent(); // no verify call made for already-paid invoice
});

// ─── BillingService::handleWebhook ───────────────────────────────────────────

it('activates tenant via webhook', function () {
    createGatewayConfig();
    $tenant = createActiveTenant(plan: 'starter', status: 'trial');

    $invoice = SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-webhook',
        'plan'           => 'growth',
        'amount'         => 1499,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'pending',
    ]);

    $service = new BillingService(new BillingGatewayManager());
    $result  = $service->handleWebhook('LENDR-SUB-webhook', 'txid-wh', 'success', 1499);

    expect($result['handled'])->toBeTrue();
    expect($result['success'])->toBeTrue();

    $tenant->refresh();
    expect($tenant->plan)->toBe('growth');
    expect($tenant->status)->toBe('active');
});

// ─── SubscriptionWebhookController ───────────────────────────────────────────

it('returns 401 for invalid flutterwave webhook signature', function () {
    createGatewayConfig();

    $response = $this->postJson('/api/v1/webhooks/subscription/flutterwave', [], [
        'verif-hash' => 'wrong-secret',
    ]);

    $response->assertStatus(401);
});

it('returns 204 and processes valid subscription webhook', function () {
    createGatewayConfig();
    $tenant = createActiveTenant(status: 'trial');

    SubscriptionInvoice::create([
        'tenant_id'      => $tenant->id,
        'gateway'        => 'flutterwave',
        'gateway_tx_ref' => 'LENDR-SUB-wh-valid',
        'plan'           => 'growth',
        'amount'         => 1499,
        'currency'       => 'ZMW',
        'billing_cycle'  => 'monthly',
        'status'         => 'pending',
    ]);

    $response = $this->postJson('/api/v1/webhooks/subscription/flutterwave', [
        'event' => 'charge.completed',
        'data'  => [
            'id'     => 12345,
            'status' => 'successful',
            'amount' => 1499,
            'tx_ref' => 'LENDR-SUB-wh-valid',
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    $response->assertNoContent();

    $tenant->refresh();
    expect($tenant->plan)->toBe('growth');
    expect($tenant->status)->toBe('active');
});

it('ignores non-subscription tx_refs in webhook', function () {
    createGatewayConfig();

    $response = $this->postJson('/api/v1/webhooks/subscription/flutterwave', [
        'event' => 'charge.completed',
        'data'  => [
            'id'     => 99,
            'status' => 'successful',
            'amount' => 500,
            'tx_ref' => 'LENDR-LOAN-abc123', // not a subscription ref
        ],
    ], [
        'verif-hash' => 'test-webhook-secret',
    ]);

    $response->assertNoContent();
    expect(SubscriptionInvoice::count())->toBe(0);
});

// ─── Landlord BillingSettingsController ──────────────────────────────────────

it('landlord can list billing gateway settings', function () {
    createGatewayConfig();

    $landlord = \App\Models\Landlord\LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/billing-settings');

    $response->assertOk()
        ->assertJsonPath('data.0.gateway', 'flutterwave')
        ->assertJsonPath('data.0.configured', true)
        ->assertJsonPath('data.0.has_secret_key', true);
});

it('landlord can activate a gateway', function () {
    createGatewayConfig(active: false);

    $landlord = \App\Models\Landlord\LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->postJson('/api/v1/landlord/billing-settings/flutterwave/activate');

    $response->assertOk();
    expect(BillingGatewayConfig::where('gateway', 'flutterwave')->value('is_active'))->toBeTrue();
});

it('landlord can update gateway credentials', function () {
    $landlord = \App\Models\Landlord\LandlordUser::factory()->create();

    $response = $this->actingAs($landlord, 'sanctum')
        ->putJson('/api/v1/landlord/billing-settings/flutterwave', [
            'secret_key'     => 'FLWSECK_TEST-newsecret',
            'webhook_secret' => 'new-webhook-hash',
        ]);

    $response->assertOk();
    $config = BillingGatewayConfig::where('gateway', 'flutterwave')->first();
    expect($config)->not->toBeNull();
    expect($config->secret_key)->toBe('FLWSECK_TEST-newsecret');
});
