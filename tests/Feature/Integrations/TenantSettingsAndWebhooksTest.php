<?php

use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\MobileMoneyIntent;
use App\Models\Tenant\Payment;
use App\Support\TenantSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Provider credentials are encrypted at rest and never sent back to the browser;
 * Enterprise-only providers stay Enterprise-only on the server; and a payment
 * provider's callback finds its workspace from the URL on the shared host.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => []]);
    $this->admin = actingAsAdmin();
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->each(fn ($t) => $t->delete()));
});

function settingsTenant(string $plan = 'growth'): Tenant
{
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'set-'.uniqid(), 'name' => 'Mwana Loans', 'slug' => 'mwana-'.uniqid(), 'plan' => $plan,
        'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka', 'email_verified_at' => now(),
    ]));
    tenancy()->initialize($tenant);

    return $tenant;
}

// ─── Settings ────────────────────────────────────────────────────────────────

it('stores provider secrets encrypted and reads them back in the clear', function () {
    settingsTenant();

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => [
        'pawapay_api_key' => 'live-token-123', 'pawapay_environment' => 'production',
    ]])->assertSessionHasNoErrors();

    $raw = DB::table('settings')->where('key', 'pawapay_api_key')->value('value');
    expect($raw)->toStartWith('enc:')->not->toContain('live-token-123')
        ->and(TenantSettings::get('pawapay_api_key'))->toBe('live-token-123')
        ->and(TenantSettings::get('pawapay_environment'))->toBe('production');
});

it('never sends a saved secret to the browser, and keeps it when the mask comes back', function () {
    settingsTenant();
    TenantSettings::put('airtel_client_secret', 's3cret');
    TenantSettings::put('lipila_api_key', '');

    $this->actingAs($this->admin)->get(route('settings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.airtel_client_secret', TenantSettings::MASK)
            ->where('settings.lipila_api_key', '')
            ->has('webhookUrls.pawapay'));

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => [
        'airtel_client_secret' => TenantSettings::MASK,
    ]])->assertSessionHasNoErrors();

    expect(TenantSettings::get('airtel_client_secret'))->toBe('s3cret');
});

it('gives each provider a callback URL that names the workspace', function () {
    $tenant = settingsTenant();

    $this->actingAs($this->admin)->get(route('settings.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('webhookUrls.mtn', route('api.v1.webhooks.tenant', ['provider' => 'mtn', 'tenant' => $tenant->id])));
});

it('keeps custom SMTP and SMS to Enterprise workspaces on the server', function () {
    settingsTenant('growth');

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => ['smtp_host' => 'mail.example.com']])
        ->assertSessionHasErrors('settings.smtp_host');

    tenancy()->end();
    settingsTenant('enterprise');

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => ['smtp_host' => 'mail.example.com']])
        ->assertSessionHasNoErrors();
});

it('refuses settings that are reserved or malformed', function () {
    settingsTenant();

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => [
        'marketplace_enabled' => '1',
        'primary_color' => 'blue',
        'fund_low_balance_threshold' => '-5',
    ]])->assertSessionHasErrors(['settings.marketplace_enabled', 'settings.primary_color', 'settings.fund_low_balance_threshold']);

    expect(DB::table('settings')->where('key', 'marketplace_enabled')->exists())->toBeFalse();
});

it('drops the staff API settings cache when settings are saved', function () {
    $tenant = settingsTenant();
    Cache::put('tenant_settings_'.$tenant->id, ['stale' => true], 600);

    $this->actingAs($this->admin)->put(route('settings.update'), ['settings' => ['company_name' => 'Mwana Loans Ltd']]);

    expect(Cache::has('tenant_settings_'.$tenant->id))->toBeFalse();
});

// ─── Webhooks on the shared host ─────────────────────────────────────────────

function pawapayCallback(Tenant $tenant, array $body, string $secret)
{
    $json = json_encode($body);

    return test()->call('POST', route('api.v1.webhooks.tenant', ['provider' => 'pawapay', 'tenant' => $tenant->id]), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_PAWAPAY_SIGNATURE' => hash_hmac('sha256', $json, $secret),
    ], $json);
}

it('records a PawaPay payment arriving at the workspace URL, signed with its encrypted secret', function () {
    $tenant = settingsTenant();
    TenantSettings::put('pawapay_webhook_secret', 'pp-secret');
    tenancy()->end(); // the callback arrives with no workspace open

    tenancy()->initialize($tenant);
    $loan = Loan::factory()->active()->create([
        'borrower_id' => Borrower::factory()->create()->id, 'principal_amount' => 2000, 'interest_amount' => 400,
        'total_payable' => 2400, 'outstanding_balance' => 2400, 'total_paid' => 0,
    ]);
    $intent = MobileMoneyIntent::create([
        'loan_id' => $loan->id, 'borrower_id' => $loan->borrower_id, 'reference' => 'LENDR-PP-1',
        'amount' => 300, 'provider' => 'pawapay', 'phone' => '+260971000001', 'status' => 'pending',
    ]);
    tenancy()->end();

    pawapayCallback($tenant, [
        'paymentId' => 'pp-001', 'status' => 'COMPLETED', 'amount' => '300.00',
        'statementDescription' => 'LENDR-PP-1', 'payer' => ['address' => ['value' => '260971000001']],
    ], 'pp-secret')->assertSuccessful();

    expect(Payment::where('loan_id', $loan->id)->count())->toBe(1)
        ->and($intent->fresh()->status)->toBe('confirmed');
});

it('rejects a callback signed with the wrong secret', function () {
    $tenant = settingsTenant();
    TenantSettings::put('pawapay_webhook_secret', 'pp-secret');
    tenancy()->end();

    pawapayCallback($tenant, ['paymentId' => 'x', 'status' => 'COMPLETED'], 'not-the-secret')->assertStatus(401);
});

it('answers 404 for an unknown workspace or provider', function () {
    $this->postJson('/api/v1/webhooks/pawapay/no-such-workspace', [])->assertNotFound();

    $tenant = settingsTenant();
    tenancy()->end();
    $this->postJson("/api/v1/webhooks/paypal/{$tenant->id}", [])->assertNotFound();
});
