<?php

use App\Enums\UserRole;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Str;

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
