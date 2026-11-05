<?php

use App\Enums\UserRole;
use App\Http\Middleware\InitializeTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;

/*
 * Starter and Trial workspaces sign in at the shared portal. That sign-in skipped
 * two-factor authentication entirely: a password alone was enough for a user who
 * had 2FA switched on.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => [], 'app.url' => 'https://lendr.test']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->each(function ($tenant) {
        $tenant->domains()->delete();
        $tenant->delete();
    }));
});

function portal2faTenant(): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'p2fa-'.uniqid(), 'name' => 'Lusaka Lenders', 'slug' => 'lusaka-'.uniqid(),
        'plan' => 'starter', 'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(),
    ]));
}

function portal2faUser(): array
{
    $secret = (new Google2FA)->generateSecretKey();
    $user = User::factory()->create([
        'role' => UserRole::SuperAdmin, 'is_active' => true, 'password' => 'Secret#12345',
    ]);
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    return [$user, $secret];
}

function portal2faReset(): void
{
    tenancy()->end();
    app('auth')->forgetGuards();
}

it('asks for the 2FA code after a portal sign-in, then lands on the portal dashboard', function () {
    $tenant = portal2faTenant();
    [$user, $secret] = portal2faUser();
    expect($user->fresh()->hasRole2faEnabled())->toBeTrue();

    $this->post('/portal/login', ['workspace' => $tenant->slug, 'email' => $user->email, 'password' => 'Secret#12345'])
        ->assertRedirect(route('2fa.challenge'));
    portal2faReset();
    $this->assertGuest('web');

    // A wrong code gets them nowhere.
    $this->post(route('2fa.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    portal2faReset();
    $this->assertGuest('web');

    $this->post(route('2fa.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
    expect(session('tenant_id'))->toBe($tenant->id)
        ->and(session(InitializeTenancy::SESSION_TENANT_KEY))->toBe($tenant->id)
        ->and(session('2fa_user_id'))->toBeNull()
        ->and($user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::where('log_name', 'auth')->where('causer_id', $user->id)->count())->toBe(1);
});

it('really signs a portal user out, records it, and sends them to the portal sign-in', function () {
    $tenant = portal2faTenant();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true, 'password' => 'Secret#12345']);

    $this->post('/portal/login', ['workspace' => $tenant->slug, 'email' => $user->email, 'password' => 'Secret#12345'])
        ->assertRedirect(route('portal.dashboard'));
    portal2faReset();

    // /portal/logout sat outside the workspace routes: 'auth' never found the user,
    // redirected, and the session stayed signed in (Suspended / Trial-ended pages use it).
    $this->post(route('portal.logout'))->assertRedirect(route('portal.login'));
    portal2faReset();

    $this->assertGuest('web');
    $this->get('/portal/dashboard')->assertRedirect(route('portal.login'));
    expect(Activity::where('log_name', 'auth')->where('causer_id', $user->id)->pluck('description')->all())
        ->toBe(['Signed in', 'Signed out']);
});

it('sends a signed-out visitor on a workspace subdomain to that subdomain\'s sign-in', function () {
    $this->get('http://acme.lendr.test/borrowers')->assertRedirect('http://acme.lendr.test/login');
});

it('still signs a portal user without 2FA straight in, and records it', function () {
    $tenant = portal2faTenant();
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true, 'password' => 'Secret#12345']);

    $this->post('/portal/login', ['workspace' => $tenant->slug, 'email' => $user->email, 'password' => 'Secret#12345'])
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
    expect($user->fresh()->last_login_at)->not->toBeNull();
});
