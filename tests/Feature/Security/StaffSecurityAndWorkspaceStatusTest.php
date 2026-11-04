<?php

use App\Commands\ProcessSubscriptionsCommand;
use App\Http\Middleware\EnforceStaffSecurity;
use App\Jobs\ProvisionTenantDatabase;
use App\Mail\SubscriptionRenewalMail;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionInvoice;
use App\Models\Landlord\Tenant;
use App\Services\Billing\BillingService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/*
 * Settings → Security is applied (forced/expired password, required 2FA, idle
 * timeout), a lapsed or suspended workspace is closed on the staff API as well as
 * the web, paid plans run out, and the landlord can create a usable workspace.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => []]);
    $this->admin = actingAsAdmin();
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(function () {
        Tenant::query()->each(function ($tenant) {
            $tenant->domains()->delete();
            $tenant->delete();
        });
    });
    Subscription::query()->delete();
    SubscriptionInvoice::query()->delete();
});

function securitySetting(string $key, string $value): void
{
    DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
}

function statusTenant(string $status, array $extra = []): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'sec-'.uniqid(), 'name' => 'Kwacha Credit', 'slug' => 'kwacha-'.uniqid(),
        'plan' => 'growth', 'status' => $status, 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(), 'admin_email' => 'owner@kwacha.test', ...$extra,
    ]));
}

// ─── Forced / expired password ───────────────────────────────────────────────

it('holds a user who must change their password on their profile, web and API', function () {
    $this->admin->forceFill(['force_password_reset' => true])->save();

    $this->actingAs($this->admin)->get(route('borrowers.index'))->assertRedirect(route('staff.profile'));
    $this->actingAs($this->admin)->get(route('staff.profile'))->assertOk();

    Sanctum::actingAs($this->admin);
    $this->getJson(route('api.v1.borrowers.index'))->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, 'new password'));
    $this->getJson(route('api.v1.auth.me'))->assertOk();
});

it('clears the forced change once the user picks a strong new password', function () {
    $this->admin->forceFill(['password' => Hash::make('Original1'), 'force_password_reset' => true])->save();

    $this->actingAs($this->admin)->put(route('staff.profile.password'), [
        'current_password' => 'Original1', 'password' => 'weakpass', 'password_confirmation' => 'weakpass',
    ])->assertSessionHasErrors('password');

    $this->actingAs($this->admin)->put(route('staff.profile.password'), [
        'current_password' => 'Original1', 'password' => 'Original1', 'password_confirmation' => 'Original1',
    ])->assertSessionHasErrors('password');

    $this->actingAs($this->admin)->put(route('staff.profile.password'), [
        'current_password' => 'Original1', 'password' => 'Brand-new-2026', 'password_confirmation' => 'Brand-new-2026',
    ])->assertSessionHasNoErrors();

    $fresh = $this->admin->fresh();
    expect($fresh->force_password_reset)->toBeFalse()
        ->and($fresh->password_changed_at)->not->toBeNull();

    $this->actingAs($fresh)->get(route('borrowers.index'))->assertOk();
});

it('asks for a new password once the workspace expiry period has passed', function () {
    securitySetting('password_expiry_days', '90');
    $this->admin->forceFill(['password_changed_at' => now()->subDays(91)])->saveQuietly();

    $this->actingAs($this->admin)->get(route('borrowers.index'))->assertRedirect(route('staff.profile'));

    $this->admin->forceFill(['password_changed_at' => now()->subDays(10)])->saveQuietly();
    $this->actingAs($this->admin->fresh())->get(route('borrowers.index'))->assertOk();
});

// ─── Required 2FA ────────────────────────────────────────────────────────────

it('requires two-factor sign-in when the workspace asks for it', function () {
    securitySetting('require_2fa', '1');

    $this->actingAs($this->admin)->get(route('borrowers.index'))->assertRedirect(route('staff.profile'));

    Sanctum::actingAs($this->admin);
    $this->postJson(route('api.v1.auth.2fa.setup'))->assertOk(); // setting it up stays reachable

    $this->admin->forceFill(['two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->admin->fresh())->get(route('borrowers.index'))->assertOk();
});

it('does not require 2FA when the workspace has it switched off', function () {
    securitySetting('require_2fa', '0');

    $this->actingAs($this->admin)->get(route('borrowers.index'))->assertOk();
});

// ─── Idle timeout ────────────────────────────────────────────────────────────

it('signs out a browser session idle for longer than the workspace allows', function () {
    securitySetting('session_timeout_minutes', '30');
    $guardKey = app('auth')->guard('web')->getName();

    $this->actingAs($this->admin)
        ->withSession([$guardKey => $this->admin->id, EnforceStaffSecurity::ACTIVITY_KEY => now()->subMinutes(45)->timestamp])
        ->get(route('borrowers.index'))
        ->assertRedirect(route('portal.login'))
        ->assertSessionHas('warning');
});

it('keeps an active session signed in and records the activity', function () {
    securitySetting('session_timeout_minutes', '30');
    $guardKey = app('auth')->guard('web')->getName();

    $this->actingAs($this->admin)
        ->withSession([$guardKey => $this->admin->id, EnforceStaffSecurity::ACTIVITY_KEY => now()->subMinutes(5)->timestamp])
        ->get(route('borrowers.index'))
        ->assertOk()
        ->assertSessionHas(EnforceStaffSecurity::ACTIVITY_KEY);
});

// ─── Workspace status ────────────────────────────────────────────────────────

it('shows an ended trial the plan page, not "suspended", and closes the staff API', function () {
    tenancy()->initialize(statusTenant('expired', ['trial_ends_at' => now()->subDays(2)]));

    $this->actingAs($this->admin)->get(route('borrowers.index'))
        ->assertStatus(402)
        ->assertInertia(fn (Assert $page) => $page->component('account/TrialExpired'));

    Sanctum::actingAs($this->admin);
    $this->getJson(route('api.v1.borrowers.index'))->assertStatus(402)->assertJsonPath('workspace_status', 'expired');
});

it('closes the staff API for a suspended workspace too', function () {
    tenancy()->initialize(statusTenant('suspended'));

    Sanctum::actingAs($this->admin);
    $this->getJson(route('api.v1.loans.index'))->assertForbidden()->assertJsonPath('workspace_status', 'suspended');

    $this->actingAs($this->admin)->get(route('borrowers.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page->component('account/Suspended')->where('supportEmail', config('lendr.support_email')));
});

// ─── Paid plans run out ──────────────────────────────────────────────────────

it('reminds the admin a week before a paid plan ends', function () {
    Mail::fake();
    $tenant = statusTenant('active');
    Subscription::create(['tenant_id' => $tenant->id, 'plan' => 'growth', 'status' => 'active', 'gateway' => 'flutterwave', 'amount' => 1499, 'currency' => 'ZMW',
        'billing_cycle' => 'monthly', 'starts_at' => now()->subMonth(), 'ends_at' => now()->addDays(7)]);

    $this->artisan(ProcessSubscriptionsCommand::class)->assertSuccessful();

    Mail::assertQueued(SubscriptionRenewalMail::class, fn ($m) => $m->stage === 'reminder' && $m->daysRemaining === 7);
    expect($tenant->fresh()->plan)->toBe('growth');
});

it('moves a workspace to Starter once its plan has lapsed past the grace period', function () {
    Mail::fake();
    $tenant = statusTenant('active');
    $sub = Subscription::create(['tenant_id' => $tenant->id, 'plan' => 'growth', 'status' => 'active', 'gateway' => 'flutterwave', 'amount' => 1499, 'currency' => 'ZMW',
        'billing_cycle' => 'monthly', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subDays(ProcessSubscriptionsCommand::GRACE_DAYS + 1)]);

    $this->artisan(ProcessSubscriptionsCommand::class, ['--dry-run' => true])->assertSuccessful();
    expect($tenant->fresh()->plan)->toBe('growth');

    $this->artisan(ProcessSubscriptionsCommand::class)->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('starter')
        ->and($sub->fresh()->status)->toBe('expired');
    Mail::assertQueued(SubscriptionRenewalMail::class, fn ($m) => $m->stage === 'ended');
});

it('leaves a plan the landlord changed by hand alone when the old subscription lapses', function () {
    Mail::fake();
    $tenant = statusTenant('active', ['plan' => 'enterprise']);
    Subscription::create(['tenant_id' => $tenant->id, 'plan' => 'growth', 'status' => 'active', 'gateway' => 'flutterwave', 'amount' => 1499, 'currency' => 'ZMW',
        'billing_cycle' => 'monthly', 'starts_at' => now()->subMonths(2), 'ends_at' => now()->subDays(10)]);

    $this->artisan(ProcessSubscriptionsCommand::class)->assertSuccessful();

    expect($tenant->fresh()->plan)->toBe('enterprise');
    Mail::assertNotQueued(SubscriptionRenewalMail::class);
});

it('schedules the subscription job', function () {
    $commands = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->map(fn ($e) => $e->command)->implode("\n");

    expect($commands)->toContain('lendr:process-subscriptions');
});

/** Calls BillingService's private activation, as a verified gateway payment does. */
function activatePaidInvoice(Tenant $tenant, string $plan = 'growth'): Subscription
{
    $invoice = SubscriptionInvoice::create([
        'tenant_id' => $tenant->id, 'plan' => $plan, 'billing_cycle' => 'monthly', 'amount' => 1499,
        'currency' => 'ZMW', 'status' => 'pending', 'gateway' => 'flutterwave', 'gateway_tx_ref' => 'tx-'.uniqid(),
    ]);

    $method = new ReflectionMethod(BillingService::class, 'activateSubscription');
    $method->invoke(app(BillingService::class), $invoice, 'gw-'.uniqid(), 1499.0);

    return Subscription::where('tenant_id', $tenant->id)->where('status', 'active')->latest('id')->firstOrFail();
}

it('adds an early renewal after the period already paid for', function () {
    $tenant = statusTenant('active');
    $first = activatePaidInvoice($tenant);
    $paidUntil = $first->ends_at->copy();

    $renewal = activatePaidInvoice($tenant);

    expect($renewal->starts_at->toDateString())->toBe($paidUntil->toDateString())
        ->and($renewal->ends_at->toDateString())->toBe($paidUntil->copy()->addMonth()->toDateString());
});

it('does not lift a suspension when a payment comes in', function () {
    $tenant = statusTenant('suspended');

    activatePaidInvoice($tenant);

    expect($tenant->fresh()->status)->toBe('suspended');

    $expired = statusTenant('expired');
    activatePaidInvoice($expired);
    expect($expired->fresh()->status)->toBe('active');
});

// ─── Landlord creates a workspace ────────────────────────────────────────────

it('creates a live, verified workspace and builds its database after the response', function () {
    Bus::fake();
    app('auth')->forgetGuards();
    $landlord = LandlordUser::factory()->create(['is_active' => true]);
    statusTenant('active', ['slug' => 'acme-finance']);

    $this->withToken($landlord->createToken('landlord-session')->plainTextToken)
        ->postJson('/api/v1/landlord/tenants', [
            'name' => 'Acme Finance', 'plan' => 'growth', 'subdomain' => 'acme',
            'admin_name' => 'Mutale Banda', 'admin_email' => 'mutale@acme.test',
        ])
        ->assertCreated();

    $tenant = Tenant::where('name', 'Acme Finance')->latest('created_at')->firstOrFail();
    expect($tenant->slug)->toBe('acme-finance-2')
        ->and($tenant->status)->toBe('active')
        ->and($tenant->email_verified_at)->not->toBeNull()
        ->and($tenant->domains()->value('domain'))->toBe('acme.'.config('tenancy.central_domain'));

    Bus::assertDispatchedAfterResponse(ProvisionTenantDatabase::class, fn ($job) => $job->tenantId === $tenant->id
        && $job->admin === ['name' => 'Mutale Banda', 'email' => 'mutale@acme.test']);
});

it('refuses a subdomain that is already taken', function () {
    app('auth')->forgetGuards();
    $landlord = LandlordUser::factory()->create(['is_active' => true]);
    statusTenant('active')->domains()->create(['domain' => 'taken.'.config('tenancy.central_domain')]);

    $this->withToken($landlord->createToken('landlord-session')->plainTextToken)
        ->postJson('/api/v1/landlord/tenants', ['name' => 'Copycat', 'plan' => 'growth', 'subdomain' => 'taken'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('subdomain');
});
