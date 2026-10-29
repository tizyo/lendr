<?php

use App\Models\Landlord\GhostUser;
use App\Models\Landlord\LandlordUser;
use PragmaRX\Google2FA\Google2FA;

/** Call a landlord route with a real bearer token, as the panel does. */
function asBearer(string $token)
{
    // Guards cache the resolved user between requests inside one test — reset so
    // each request authenticates with the token it actually sends.
    app('auth')->forgetGuards();

    return test()->withHeaders(['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json']);
}

function landlordWith2fa(): array
{
    $secret = (new Google2FA)->generateSecretKey();
    $landlord = LandlordUser::factory()->create(['email' => 'boss@lendr.test', 'password' => 'Secret#12345', 'is_active' => true]);
    $landlord->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

    return [$landlord, $secret];
}

// ─── Who may use the landlord API ────────────────────────────────────────────

it('refuses marketplace (ghost) user tokens on landlord routes', function () {
    $ghost = GhostUser::create(['phone' => '0977123456', 'name' => 'Repo Buyer']);
    $token = $ghost->createToken('marketplace')->plainTextToken;

    asBearer($token)->getJson('/api/v1/landlord/tenants')->assertForbidden();
    asBearer($token)->getJson('/api/v1/landlord/platform-settings/email')->assertForbidden();
});

it('does not let a password-only (pre-2FA) token into the landlord API', function () {
    [$landlord] = landlordWith2fa();

    $preAuth = $this->postJson('/api/v1/landlord/auth/login', ['email' => 'boss@lendr.test', 'password' => 'Secret#12345'])
        ->assertOk()
        ->json('pre_auth_token');

    asBearer($preAuth)->getJson('/api/v1/landlord/tenants')->assertForbidden();
    asBearer($preAuth)->getJson('/api/v1/landlord/auth/me')->assertForbidden();
});

it('does not let a 2FA-setup token into the landlord API', function () {
    $landlord = LandlordUser::factory()->create(['is_active' => true]);
    $setup = $landlord->createToken('2fa-setup', ['2fa-setup'])->plainTextToken;

    asBearer($setup)->getJson('/api/v1/landlord/tenants')->assertForbidden();
});

it('completes login with the 2FA code and then allows the landlord API', function () {
    [$landlord, $secret] = landlordWith2fa();

    $preAuth = $this->postJson('/api/v1/landlord/auth/login', ['email' => 'boss@lendr.test', 'password' => 'Secret#12345'])
        ->assertJsonPath('two_factor', true)
        ->json('pre_auth_token');

    $session = asBearer($preAuth)
        ->postJson('/api/v1/landlord/auth/2fa/challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk()
        ->json('data.token');

    asBearer($session)->getJson('/api/v1/landlord/tenants')->assertOk();
    asBearer($session)->getJson('/api/v1/landlord/auth/me')->assertOk()->assertJsonPath('data.email', 'boss@lendr.test');
});

it('only accepts a 2FA challenge with a pre-auth token', function () {
    [$landlord, $secret] = landlordWith2fa();
    $ghost = GhostUser::create(['phone' => '0977000999', 'name' => 'X']);

    asBearer($ghost->createToken('marketplace')->plainTextToken)
        ->postJson('/api/v1/landlord/auth/2fa/challenge', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertForbidden();
});
