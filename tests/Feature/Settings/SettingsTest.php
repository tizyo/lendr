<?php

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

function settingsUser(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

// ─── Index ────────────────────────────────────────────────────────────────────

test('settings index returns grouped settings', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.settings.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [
            'general',
            'branding',
            'smtp',
            'sms',
            'security',
        ]]);
});

test('settings returns stored values', function () {
    $user = settingsUser();

    DB::table('settings')->updateOrInsert(
        ['key' => 'company_name'],
        ['value' => 'Test MFI', 'updated_at' => now()]
    );

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.settings.index'));

    expect($response->json('data.general.company_name'))->toBe('Test MFI');
});

test('masked keys are not returned in plaintext', function () {
    $user = settingsUser();

    DB::table('settings')->updateOrInsert(
        ['key' => 'smtp_password'],
        ['value' => 'super_secret_123', 'updated_at' => now()]
    );

    $response = $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.settings.index'));

    // smtp_password is not in general group, it's in smtp group
    // but it should be masked regardless
    expect($response->json('data.smtp.smtp_password') ?? '')->not->toBe('super_secret_123');
});

// ─── Update ───────────────────────────────────────────────────────────────────

test('settings can be updated', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.settings.update'), [
            'settings' => [
                'company_name'  => 'Lendr MFI',
                'currency'      => 'ZMW',
                'company_email' => 'admin@lendr.zm',
            ],
        ])
        ->assertOk();

    expect(DB::table('settings')->where('key', 'company_name')->value('value'))->toBe('Lendr MFI');
    expect(DB::table('settings')->where('key', 'currency')->value('value'))->toBe('ZMW');
});

test('settings update requires settings array', function () {
    $user = settingsUser();

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.settings.update'), [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['settings']);
});

test('masked placeholder value does not overwrite stored secret', function () {
    $user = settingsUser();

    DB::table('settings')->updateOrInsert(
        ['key' => 'smtp_password'],
        ['value' => 'real_secret', 'updated_at' => now()]
    );

    $this->actingAs($user)
        ->withHeaders(['Accept' => 'application/json'])
        ->putJson(route('api.v1.settings.update'), [
            'settings' => ['smtp_password' => '••••••••'],
        ])
        ->assertOk();

    // Should NOT have overwritten with the placeholder
    expect(DB::table('settings')->where('key', 'smtp_password')->value('value'))->toBe('real_secret');
});

// ─── Branding (public) ────────────────────────────────────────────────────────

test('branding endpoint is publicly accessible', function () {
    $this->withHeaders(['Accept' => 'application/json'])
        ->getJson(route('api.v1.settings.branding'))
        ->assertOk();
});
