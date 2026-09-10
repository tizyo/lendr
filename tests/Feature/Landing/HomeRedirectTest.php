<?php

use App\Enums\UserRole;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// ─── Cleanup: purge any tenant DB connections created during this test ────────
afterEach(function () {
    // End active tenancy so the connection reverts to the root DB.
    // Without this, a test that ends mid-tenant-session (e.g. via a login
    // request that leaves tenancy active) orphans RefreshDatabase's
    // transaction wrapper on the central connection, breaking every
    // subsequent test class in the run.
    if (tenancy()->initialized) {
        tenancy()->end();
    }

    // Purge all dynamically-created tenant DB connections and delete their files.
    Tenant::all()->each(function (Tenant $tenant) {
        try {
            DB::purge('tenant');

            $dbFile = database_path('tenant'.$tenant->id);
            if (file_exists($dbFile)) {
                unlink($dbFile);
            }

            $tenant->delete();
        } catch (\Throwable) {
            // Ignore cleanup errors — best effort.
        }
    });
});

function landingTenant(): Tenant
{
    return Tenant::create([
        'id' => (string) Str::uuid(),
        'name' => 'Landing Redirect Test MFI',
        'slug' => 'landing-redirect-'.uniqid(),
        'plan' => 'starter',
        'status' => 'trial',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(),
    ]);
}

test('guest visiting the landing page sees the marketing page', function () {
    $this->get(route('home'))
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Landing/Home'));
});

test('staff with an active portal session are redirected from the landing page to their dashboard', function () {
    $tenant = landingTenant();
    tenancy()->initialize($tenant);
    User::factory()->create([
        'email' => 'owner@landingredirect.com',
        'password' => bcrypt('Password1!'),
        'role' => UserRole::SuperAdmin,
        'is_active' => true,
    ]);
    tenancy()->end();

    $this->post(route('portal.login.post'), [
        'workspace' => $tenant->slug,
        'email' => 'owner@landingredirect.com',
        'password' => 'Password1!',
    ])->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticated();
    $this->assertEquals($tenant->id, session('tenant_id'));

    // Now hit the landing page with that same active session.
    $this->get(route('home'))
        ->assertRedirect(route('portal.dashboard'));
});
