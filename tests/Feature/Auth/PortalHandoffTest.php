<?php

use App\Enums\UserRole;
use App\Http\Middleware\InitializeTenancy;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;

/*
 * Staff of a workspace with its own subdomain who sign in at the shared portal are
 * carried over to their subdomain, signed in, instead of being told to go there.
 */

beforeEach(function () {
    // Tests share one database, so tenancy can initialize for real without switching it.
    config(['tenancy.bootstrappers' => [], 'app.url' => 'https://lendr.test']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->each(function ($tenant) {
        $tenant->domains()->delete();
        $tenant->delete();
    }));
});

function handoffTenant(?string $domain, string $plan = 'growth'): Tenant
{
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'handoff-'.uniqid(),
        'name' => 'Jodal Finance',
        'slug' => 'jodal-'.uniqid(),
        'plan' => $plan,
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(),
    ]));

    if ($domain) {
        $tenant->domains()->create(['domain' => $domain]);
    }

    return $tenant;
}

function handoffStaff(array $overrides = []): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true, 'password' => 'Secret#12345', ...$overrides]);
}

/** Signs in at the portal and returns where it sent the browser. */
function portalSignIn(Tenant $tenant, string $email, string $password = 'Secret#12345')
{
    $response = test()->post('/portal/login', ['workspace' => $tenant->slug, 'email' => $email, 'password' => $password]);
    tenancy()->end();
    app('auth')->forgetGuards();

    return $response;
}

it('carries a subdomain workspace sign-in over from the portal', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();

    $response = portalSignIn($tenant, $user->email);

    $link = $response->headers->get('Location');
    expect($link)->toStartWith('https://jodal.lendr.test/login/handoff?token=');
    $this->assertGuest('web');

    $this->get(str_replace('https://', 'http://', $link))->assertRedirect('http://jodal.lendr.test/dashboard');
    $this->assertAuthenticatedAs($user, 'web');
    expect(session(InitializeTenancy::SESSION_TENANT_KEY))->toBe($tenant->id);
});

it('still refuses a wrong password, without a link', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();

    portalSignIn($tenant, $user->email, 'wrong-password')
        ->assertSessionHasErrors('email')
        ->assertSessionDoesntHaveErrors('workspace');
});

it('lets a sign-in link be used only once', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();
    $link = str_replace('https://', 'http://', portalSignIn($tenant, $user->email)->headers->get('Location'));

    $this->get($link);
    app('auth')->guard('web')->logout();
    session()->flush();
    tenancy()->end();

    $this->get($link)->assertRedirect('http://jodal.lendr.test/login')->assertSessionHasErrors('email');
    $this->assertGuest('web');
});

it('refuses an expired sign-in link', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();
    $link = str_replace('https://', 'http://', portalSignIn($tenant, $user->email)->headers->get('Location'));

    $this->travel(2)->minutes();

    $this->get($link)->assertRedirect('http://jodal.lendr.test/login');
    $this->assertGuest('web');
});

it('refuses a sign-in link on another workspace\'s subdomain', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    handoffTenant('other.lendr.test');
    $user = handoffStaff();
    $link = portalSignIn($tenant, $user->email)->headers->get('Location');

    $this->get(str_replace('https://jodal.', 'http://other.', $link))->assertRedirect('http://other.lendr.test/login');
    $this->assertGuest('web');
});

it('still asks for the 2FA code after a carried-over sign-in', function () {
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    $link = str_replace('https://', 'http://', portalSignIn($tenant, $user->email)->headers->get('Location'));

    $this->get($link)->assertRedirect('http://jodal.lendr.test/2fa/challenge');
    $this->assertGuest('web');
});

// ─── While subdomains aren't served (production today: no wildcard DNS) ──────

it('signs a subdomain workspace straight into the portal while subdomains are off', function () {
    config(['tenancy.subdomains' => false]);
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();

    portalSignIn($tenant, $user->email)->assertRedirect(route('portal.dashboard'));
    expect($tenant->loginUrl())->toBe(route('portal.login'));
});

it('lets portal staff of a subdomain workspace work there while subdomains are off', function () {
    config(['tenancy.subdomains' => false]);
    $tenant = handoffTenant('jodal.lendr.test');
    $user = handoffStaff();

    portalSignIn($tenant, $user->email);

    $this->get('/staff/profile')->assertOk();
    $this->assertAuthenticatedAs($user, 'web');
});

it('signs a growth workspace without a subdomain straight into the portal', function () {
    $tenant = handoffTenant(null);
    $user = handoffStaff();

    portalSignIn($tenant, $user->email)->assertRedirect(route('portal.dashboard'));
});
