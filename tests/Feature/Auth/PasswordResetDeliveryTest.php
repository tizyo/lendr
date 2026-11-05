<?php

use App\Enums\UserRole;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\Http;

/*
 * "Forgot password" on the shared portal: the link is sent, and when the email
 * provider refuses it the person is told so instead of seeing a server error
 * (or being told it was sent).
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => [], 'app.url' => 'https://lendr.test']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->each(function ($t) {
        $t->domains()->delete();
        $t->delete();
    }));
    PlatformEmailConfig::query()->delete();
});

function resetWorkspace(): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Mongu Microcredit', 'slug' => 'mongu',
        'plan' => 'starter', 'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka', 'email_verified_at' => now(),
    ]));
}

it('emails a portal reset link through the configured provider, matching the ID and email in any case', function () {
    resetWorkspace();
    User::factory()->create(['email' => 'teller@mongu.test', 'role' => UserRole::LoanOfficer, 'is_active' => true]);
    PlatformEmailConfig::create(['driver' => 'resend', 'api_key' => 're_key', 'from_address' => 'hello@lendr.test', 'is_active' => true]);
    Http::fake(['api.resend.com/emails' => Http::response(['id' => (string) \Illuminate\Support\Str::uuid()])]);

    $this->post('/portal/forgot-password', ['workspace' => 'MONGU', 'email' => 'Teller@Mongu.test'])
        ->assertSessionHas('status')
        ->assertSessionHasNoErrors();

    Http::assertSent(fn ($r) => $r['to'] === ['teller@mongu.test']
        && str_contains($r['html'], '/portal/reset-password?token=')
        && str_contains($r['html'], 'workspace=mongu'));
    expect(\Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', 'teller@mongu.test')->exists())->toBeTrue();
});

it('says the email could not be sent when the provider refuses it', function () {
    resetWorkspace();
    User::factory()->create(['email' => 'teller@mongu.test', 'role' => UserRole::LoanOfficer, 'is_active' => true]);
    PlatformEmailConfig::create(['driver' => 'resend', 'api_key' => 're_key', 'from_address' => 'hello@lendr.test', 'is_active' => true]);
    Http::fake(['api.resend.com/emails' => Http::response(['message' => 'Domain not verified'], 403)]);

    $this->post('/portal/forgot-password', ['workspace' => 'mongu', 'email' => 'teller@mongu.test'])
        ->assertSessionHasErrors(['email' => 'We couldn\'t send the reset email just now. Please try again in a few minutes.'])
        ->assertSessionMissing('status');

    expect(tenancy()->initialized)->toBeFalse(); // left the workspace even though it failed
});
