<?php

use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * A plan-gated page (e.g. Collections on Starter) shows a real upgrade page inside
 * the app layout, priced in Kwacha from plan_configs, whose button goes to Billing
 * with the right plan preselected, instead of a dollar notice and a mailto link.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => []]);
    PlanConfig::updateOrCreate(['plan' => 'starter'], ['label' => 'Starter', 'price_zmw' => 0, 'is_custom_price' => false,
        'features' => ['max_users' => 3, 'collection_management' => false, 'pwa' => false]]);
    PlanConfig::updateOrCreate(['plan' => 'growth'], ['label' => 'Growth', 'price_zmw' => 1499, 'is_custom_price' => false,
        'features' => ['max_users' => 15, 'collection_management' => true, 'pwa' => true]]);
    PlanConfig::updateOrCreate(['plan' => 'enterprise'], ['label' => 'Enterprise', 'price_zmw' => 0, 'is_custom_price' => true,
        'features' => ['max_users' => -1, 'collection_management' => true, 'pwa' => true]]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'upgrade-%')->each(fn ($t) => $t->delete()));
});

function upgradeTenant(string $plan): Tenant
{
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'upgrade-'.uniqid(), 'name' => 'Cashplus', 'slug' => 'cashplus-'.uniqid(), 'plan' => $plan,
        'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka', 'email_verified_at' => now(),
    ]));
    tenancy()->initialize($tenant);

    return $tenant;
}

it('shows a Kwacha upgrade page with the next plan that includes the feature', function () {
    $admin = actingAsAdmin();
    upgradeTenant('starter');

    $this->actingAs($admin)->get(route('collections.index'))
        ->assertStatus(402)
        ->assertInertia(fn (Assert $page) => $page
            ->component('upgrade/PremiumRequired')
            ->where('feature', 'Collection Management')
            ->where('current.key', 'starter')
            ->where('recommended.key', 'growth')
            ->where('recommended.price_zmw', 1499)
            ->where('recommended.features.0.key', 'collection_management')
            ->where('recommended.features.0.included', true)
            ->where('current.features.0.included', false));
});

it('lets the page through once the plan includes the feature', function () {
    $admin = actingAsAdmin();
    upgradeTenant('growth');

    $this->actingAs($admin)->get(route('collections.index'))->assertOk();
});

it('tells borrowers plainly when the lender has not switched the app on', function () {
    upgradeTenant('starter');

    $this->get(route('pwa.auth.login'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Error')
            ->where('title', 'App not available yet')
            ->where('message', fn ($m) => str_contains($m, 'Cashplus')));
});

it('preselects the plan on Billing when sent from the upgrade button', function () {
    $admin = actingAsAdmin();
    upgradeTenant('starter');

    $this->actingAs($admin)->get(route('billing.index', ['plan' => 'growth']))
        ->assertInertia(fn (Assert $page) => $page->where('preselect', 'growth'));

    $this->actingAs($admin)->get(route('billing.index', ['plan' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page->where('preselect', null));
});
