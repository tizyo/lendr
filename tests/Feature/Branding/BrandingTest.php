<?php

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function brandingAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

// ─── GET branding ─────────────────────────────────────────────────────────────

test('branding endpoint is publicly accessible without auth', function () {
    $this->getJson(route('api.v1.branding.show'))
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['company_name', 'primary_color', 'secondary_color', 'pwa_app_name'],
        ]);
})->group('branding');

test('branding returns defaults when nothing is configured', function () {
    $resp = $this->getJson(route('api.v1.branding.show'))->assertOk();

    expect($resp->json('data.company_name'))->toBe('LENDR');
    expect($resp->json('data.primary_color'))->toBe('#0D47A1');
})->group('branding');

// ─── PUT branding ─────────────────────────────────────────────────────────────

test('admin can update branding settings', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.branding.update'), [
            'company_name' => 'MicroBank',
            'primary_color' => '#1B5E20',
            'support_email' => 'support@microbank.com',
        ])
        ->assertOk();

    $resp = $this->getJson(route('api.v1.branding.show'))->assertOk();
    expect($resp->json('data.company_name'))->toBe('MicroBank');
    expect($resp->json('data.primary_color'))->toBe('#1B5E20');
    expect($resp->json('data.support_email'))->toBe('support@microbank.com');
})->group('branding');

test('color must be valid hex code', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.branding.update'), ['primary_color' => 'not-a-color'])
        ->assertJsonValidationErrors(['primary_color']);

    $this->actingAs($admin)
        ->putJson(route('api.v1.branding.update'), ['primary_color' => '#ZZZ000'])
        ->assertJsonValidationErrors(['primary_color']);
})->group('branding');

test('support_email must be valid email', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.branding.update'), ['support_email' => 'not-an-email'])
        ->assertJsonValidationErrors(['support_email']);
})->group('branding');

test('website must be valid url', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.v1.branding.update'), ['website' => 'not-a-url'])
        ->assertJsonValidationErrors(['website']);
})->group('branding');

test('unauthenticated cannot update branding', function () {
    $this->putJson(route('api.v1.branding.update'), ['company_name' => 'Hack'])
        ->assertStatus(401);
})->group('branding');

// ─── Logo upload ──────────────────────────────────────────────────────────────

test('admin can upload a logo', function () {
    Storage::fake('public');
    $admin = brandingAdmin();

    $file = UploadedFile::fake()->image('logo.png', 200, 80);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.branding.logo'), ['logo' => $file])
        ->assertOk();

    expect($resp->json('data.url'))->toBeString()->not->toBeEmpty();
})->group('branding');

test('logo upload requires an image', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.branding.logo'), [])
        ->assertJsonValidationErrors(['logo']);
})->group('branding');

test('logo upload rejects non-image files', function () {
    Storage::fake('public');
    $admin = brandingAdmin();

    $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $this->actingAs($admin)
        ->postJson(route('api.v1.branding.logo'), ['logo' => $file])
        ->assertJsonValidationErrors(['logo']);
})->group('branding');

// ─── Favicon upload ───────────────────────────────────────────────────────────

test('admin can upload a favicon', function () {
    Storage::fake('public');
    $admin = brandingAdmin();

    $file = UploadedFile::fake()->image('favicon.png', 32, 32);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.branding.favicon'), ['favicon' => $file])
        ->assertOk();

    expect($resp->json('data.url'))->toBeString()->not->toBeEmpty();
})->group('branding');

test('favicon upload requires a file', function () {
    $admin = brandingAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.branding.favicon'), [])
        ->assertJsonValidationErrors(['favicon']);
})->group('branding');
