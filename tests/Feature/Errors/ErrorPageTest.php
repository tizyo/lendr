<?php

use App\Enums\UserRole;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Support\ErrorPage;
use Illuminate\Support\Facades\DB;

/*
 * Error screens are branded pages (the lender's colour and logo inside a workspace,
 * LENDR's elsewhere) instead of the bare framework page, which Inertia showed in a
 * dark modal over the app. API requests keep their JSON errors.
 */

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->where('id', 'like', 'errpage-%')->each(fn ($t) => $t->delete()));
});

it('shows the branded page for an unknown URL', function () {
    $this->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page->component('Error')
            ->where('status', 404)
            ->where('title', 'Page not found')
            ->where('brand.name', 'LENDR'));
});

it('shows the branded page when staff lack permission', function () {
    actingAsAdmin(); // seeds roles
    $cashier = User::factory()->create(['role' => UserRole::Cashier, 'is_active' => true]);

    $this->actingAs($cashier)->get(route('loan-types.index'))
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->component('Error')->where('status', 403));
});

it('keeps JSON errors for API requests', function () {
    $this->getJson('/api/v1/this-does-not-exist')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('uses the lender\'s brand colour inside their workspace', function () {
    config(['tenancy.bootstrappers' => []]);
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'errpage-'.uniqid(),
        'name' => 'Kafue Credit',
        'slug' => 'kafue-'.uniqid(),
        'plan' => 'growth',
        'status' => 'active',
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(),
    ]));
    tenancy()->initialize($tenant);
    DB::table('settings')->updateOrInsert(['key' => 'primary_color'], ['value' => '#16A34A']);

    $brand = ErrorPage::brand();

    expect($brand['color'])->toBe('#16A34A')
        ->and($brand['on_color'])->toBe('#FFFFFF')
        ->and($brand['name'])->toBe('KAFUE CREDIT'); // names are stored in capitals
});

it('renders the plain fallback pages too', function (int $status, string $title) {
    $html = view("errors.{$status}")->render();

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->toContain($title)
        ->toContain('--brand: #2563EB');
})->with([
    [403, 'Access denied'],
    [404, 'Page not found'],
    [419, 'Page expired'],
    [429, 'Too many requests'],
    [500, 'Something went wrong'],
    [503, 'Back soon'],
]);

it('falls back to LENDR blue outside a workspace', function () {
    expect(ErrorPage::brand())->toMatchArray(['name' => 'LENDR', 'color' => '#2563EB', 'is_tenant' => false]);
});
