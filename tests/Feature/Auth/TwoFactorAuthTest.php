<?php

use App\Enums\UserRole;
use App\Models\Tenant\User;
use PragmaRX\Google2FA\Google2FA;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function twoFaUser(): User
{
    return User::factory()->create([
        'role' => UserRole::LoanOfficer,
        'is_active' => true,
        'password' => bcrypt('Secret@123'),
    ]);
}

function userWithTwoFaEnabled(): User
{
    $user = twoFaUser();
    $secret = (new Google2FA)->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();

    return $user;
}

// ─── 2FA Setup ────────────────────────────────────────────────────────────────

test('authenticated user can request 2fa setup', function () {
    $user = twoFaUser();

    $response = $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.setup'))
        ->assertOk();

    expect($response->json('data.secret'))->not->toBeEmpty();
    expect($response->json('data.qr_code_url'))->not->toBeEmpty();

    $user->refresh();
    expect($user->two_factor_secret)->not->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull(); // not confirmed yet
})->group('2fa');

test('setup overwrites any previous secret and clears confirmation', function () {
    $user = userWithTwoFaEnabled();

    $oldSecret = $user->two_factor_secret;

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.setup'))
        ->assertOk();

    $user->refresh();
    expect($user->two_factor_secret)->not->toEqual($oldSecret);
    expect($user->two_factor_confirmed_at)->toBeNull();
})->group('2fa');

test('unauthenticated user cannot call 2fa setup', function () {
    $this->postJson(route('api.v1.auth.2fa.setup'))
        ->assertUnauthorized();
})->group('2fa');

// ─── 2FA Verify (confirm setup) ───────────────────────────────────────────────

test('user can confirm 2fa setup with valid totp code', function () {
    $user = twoFaUser();
    $google = new Google2FA;
    $secret = $google->generateSecretKey();

    $user->forceFill(['two_factor_secret' => $secret])->save();

    $code = $google->getCurrentOtp($secret);

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.verify'), ['code' => $code])
        ->assertOk()
        ->assertJsonPath('message', '2FA enabled successfully.');

    $user->refresh();
    expect($user->two_factor_confirmed_at)->not->toBeNull();
})->group('2fa');

test('verify fails with invalid totp code', function () {
    $user = twoFaUser();
    $secret = (new Google2FA)->generateSecretKey();
    $user->forceFill(['two_factor_secret' => $secret])->save();

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.verify'), ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid authentication code.');
})->group('2fa');

test('verify fails when 2fa not yet set up', function () {
    $user = twoFaUser(); // no secret stored

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.verify'), ['code' => '123456'])
        ->assertUnprocessable();
})->group('2fa');

test('verify requires a 6-digit code', function () {
    $user = twoFaUser();

    $this->actingAs($user)
        ->postJson(route('api.v1.auth.2fa.verify'), ['code' => '123'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->group('2fa');

// ─── 2FA Disable ──────────────────────────────────────────────────────────────

test('user can disable 2fa', function () {
    $user = userWithTwoFaEnabled();

    $this->actingAs($user)
        ->deleteJson(route('api.v1.auth.2fa.disable'))
        ->assertOk()
        ->assertJsonPath('message', '2FA disabled.');

    $user->refresh();
    expect($user->two_factor_secret)->toBeNull();
    expect($user->two_factor_confirmed_at)->toBeNull();
})->group('2fa');

test('unauthenticated user cannot disable 2fa', function () {
    $this->deleteJson(route('api.v1.auth.2fa.disable'))
        ->assertUnauthorized();
})->group('2fa');

// ─── 2FA Challenge (login flow) ───────────────────────────────────────────────

test('login returns two_factor flag and pre_auth_token when 2fa is enabled', function () {
    $user = userWithTwoFaEnabled();

    $response = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    expect($response->json('two_factor'))->toBeTrue();
    expect($response->json('pre_auth_token'))->not->toBeEmpty();
    expect($response->json('data'))->toBeNull(); // no full token yet
})->group('2fa');

test('challenge returns full token on valid totp code', function () {
    $user = twoFaUser();
    $google = new Google2FA;
    $secret = $google->generateSecretKey();

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_confirmed_at' => now(),
    ])->save();

    // Get pre-auth token
    $loginResp = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    $preAuthToken = $loginResp->json('pre_auth_token');

    $code = $google->getCurrentOtp($secret);

    $response = $this->withToken($preAuthToken)
        ->postJson(route('api.v1.auth.2fa.challenge'), ['code' => $code])
        ->assertOk();

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('data.user'))->not->toBeEmpty();
})->group('2fa');

test('challenge rejects invalid totp code', function () {
    $user = userWithTwoFaEnabled();

    $loginResp = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    $preAuthToken = $loginResp->json('pre_auth_token');

    $this->withToken($preAuthToken)
        ->postJson(route('api.v1.auth.2fa.challenge'), ['code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Invalid authentication code.');
})->group('2fa');

test('challenge requires 6-digit code', function () {
    $user = userWithTwoFaEnabled();

    $loginResp = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    $preAuthToken = $loginResp->json('pre_auth_token');

    $this->withToken($preAuthToken)
        ->postJson(route('api.v1.auth.2fa.challenge'), ['code' => '12345'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->group('2fa');

test('normal login (no 2fa) returns token directly', function () {
    $user = twoFaUser(); // 2FA not enabled

    $response = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    expect($response->json('data.token'))->not->toBeEmpty();
    expect($response->json('two_factor'))->toBeNull();
})->group('2fa');

test('login with 2fa enabled does not expose full token until challenge passed', function () {
    $user = userWithTwoFaEnabled();

    $response = $this->postJson(route('api.v1.auth.login'), [
        'login' => $user->email,
        'password' => 'Secret@123',
    ])->assertOk();

    // Full token must NOT be in the response
    expect($response->json('data.token'))->toBeNull();
    expect($response->json('token'))->toBeNull();
})->group('2fa');
