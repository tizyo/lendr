<?php

use App\Enums\UserRole;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

// ─── Cleanup: purge any tenant DB connections created during this test ────────
afterEach(function () {
    // End active tenancy so the connection reverts to the root DB.
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // Purge all dynamically-created tenant DB connections and delete their files.
    Tenant::all()->each(function (Tenant $tenant) {
        try {
            // Disconnect the tenant connection if it was established.
            DB::purge('tenant');

            // For SQLite: delete the physical DB file (prefix = 'tenant', no suffix).
            $dbFile = database_path('tenant' . $tenant->id);
            if (file_exists($dbFile)) {
                unlink($dbFile);
            }

            $tenant->delete();
        } catch (\Throwable) {
            // Ignore cleanup errors — best effort.
        }
    });
});

// ─── Form helper ─────────────────────────────────────────────────────────────
function starterPayload(array $overrides = []): array
{
    return array_merge([
        'org_name'              => 'Test MFI',
        'slug'                  => 'testmfi-' . uniqid(),
        'plan'                  => 'starter',
        'currency'              => 'ZMW',
        'timezone'              => 'Africa/Lusaka',
        'admin_name'            => 'Admin User',
        'admin_email'           => 'admin@testmfi.com',
        'admin_phone'           => '+260977000001',
        'admin_password'        => 'Password1!',
        'admin_password_confirmation' => 'Password1!',
    ], $overrides);
}

// ─── Page rendering ───────────────────────────────────────────────────────────
test('onboarding page renders correctly', function () {
    $this->get(route('onboarding'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Index')
            ->has('plans')
            ->has('currencies')
            ->has('timezones')
            ->has('sharedPortalHost')
        );
});

test('success page redirects to onboarding when no session', function () {
    $this->get(route('onboarding.success'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('onboarding/Index'));
});

// ─── Starter plan (shared portal) ────────────────────────────────────────────
test('starter plan creates tenant and superadmin user', function () {
    $payload = starterPayload(['slug' => 'starter-e2e-' . uniqid()]);

    $response = $this->post(route('onboarding.store'), $payload);

    $response->assertRedirect(route('onboarding.success'));

    // Tenant record created in central DB
    $tenant = Tenant::where('slug', $payload['slug'])->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->plan)->toBe('starter')
        ->and($tenant->currency)->toBe('ZMW')
        ->and($tenant->status)->toBe('trial');

    // No domain record for shared-portal plans
    expect(Domain::where('tenant_id', $tenant->id)->exists())->toBeFalse();

    // SuperAdmin user created in tenant DB (inactive until email verified)
    tenancy()->initialize($tenant);
    $user = \App\Models\Tenant\User::where('email', $payload['admin_email'])->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe(UserRole::SuperAdmin)
        ->and($user->is_active)->toBeFalse();
    tenancy()->end();
});

test('success page shows tenant details after onboarding', function () {
    $payload = starterPayload(['slug' => 'success-e2e-' . uniqid()]);

    $this->post(route('onboarding.store'), $payload);

    $this->get(route('onboarding.success'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('onboarding/Success')
            ->where('slug', $payload['slug'])
            ->where('plan', 'starter')
            ->where('adminEmail', $payload['admin_email'])
            ->where('orgName', $payload['org_name'])
        );
});

// ─── Growth plan (custom subdomain) ──────────────────────────────────────────
test('growth plan creates tenant with custom subdomain', function () {
    $slug      = 'growth-e2e-' . uniqid();
    $subdomain = 'growth-' . uniqid();
    $payload   = starterPayload([
        'plan'      => 'growth',
        'slug'      => $slug,
        'subdomain' => $subdomain,
    ]);

    $response = $this->post(route('onboarding.store'), $payload);
    $response->assertRedirect(route('onboarding.success'));

    $tenant = Tenant::where('slug', $slug)->first();
    expect($tenant)->not->toBeNull()
        ->and($tenant->plan)->toBe('growth');

    $centralDomain = env('CENTRAL_DOMAIN', 'localhost');
    $domain        = Domain::where('domain', $subdomain . '.' . $centralDomain)->first();
    expect($domain)->not->toBeNull()
        ->and($domain->tenant_id)->toBe($tenant->id);
});

// ─── Validation ───────────────────────────────────────────────────────────────
test('onboarding requires all required fields', function () {
    $this->post(route('onboarding.store'), [])
        ->assertSessionHasErrors(['org_name', 'slug', 'plan', 'currency', 'timezone', 'admin_name', 'admin_email', 'admin_password']);
});

test('duplicate slug is rejected', function () {
    $slug    = 'dup-slug-' . uniqid();
    $payload = starterPayload(['slug' => $slug]);

    $this->post(route('onboarding.store'), $payload);
    $this->post(route('onboarding.store'), starterPayload(['slug' => $slug]))
        ->assertSessionHasErrors('slug');
});

test('reserved subdomain is rejected for growth plan', function () {
    $payload = starterPayload([
        'plan'      => 'growth',
        'subdomain' => 'api',
    ]);

    $this->post(route('onboarding.store'), $payload)
        ->assertSessionHasErrors('subdomain');
});

test('password confirmation must match', function () {
    $payload = starterPayload([
        'admin_password'              => 'Password1!',
        'admin_password_confirmation' => 'DifferentPass!',
    ]);

    $this->post(route('onboarding.store'), $payload)
        ->assertSessionHasErrors('admin_password');
});

// ─── InitializeTenancy middleware ─────────────────────────────────────────────
test('shared portal login page is accessible without tenant context', function () {
    $this->get(route('portal.login'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('auth/Login')
            ->where('isPortal', true)
        );
});

test('session-based tenancy resolves correct tenant after portal login', function () {
    // Create a starter tenant
    $slug    = 'session-e2e-' . uniqid();
    $payload = starterPayload([
        'slug'        => $slug,
        'admin_email' => 'portal@sessiontest.com',
    ]);
    $this->post(route('onboarding.store'), $payload);

    $tenant = Tenant::where('slug', $slug)->firstOrFail();

    // Simulate email verification so login is not blocked.
    $tenant->update(['email_verified_at' => now()]);
    tenancy()->initialize($tenant);
    \App\Models\Tenant\User::where('email', 'portal@sessiontest.com')->update(['is_active' => true]);
    tenancy()->end();

    // Portal login should set tenant_id in session
    $response = $this->post(route('portal.login.post'), [
        'workspace' => $slug,
        'email'     => 'portal@sessiontest.com',
        'password'  => 'Password1!',
    ]);

    $response->assertRedirect();
    $this->assertAuthenticated();

    // Session should contain the correct tenant_id
    $this->assertEquals($tenant->id, session('tenant_id'));
});
