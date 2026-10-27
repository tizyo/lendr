<?php

use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\Tenant;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    config(['mail.from' => ['address' => 'noreply@lendr.app', 'name' => 'LENDR']]);
});

afterEach(function () {
    tenancy()->tenant = null;
    tenancy()->initialized = false;
});

function activeResendConfig(array $overrides = []): PlatformEmailConfig
{
    return PlatformEmailConfig::create(array_merge([
        'driver' => 'resend',
        'api_key' => 're_test_123',
        'from_address' => 'mail@lendr.app',
        'from_name' => 'LENDR',
        'is_active' => true,
    ], $overrides));
}

/** Pretend we're inside a tenant request without swapping the DB connection. */
function actAsTenantContext(string $slug): void
{
    tenancy()->tenant = (new Tenant)->forceFill(['slug' => $slug, 'plan' => 'growth']);
    tenancy()->initialized = true;
}

function fakeResend(): void
{
    Http::fake(['api.resend.com/*' => Http::response(['id' => 'msg_1'], 200)]);
}

// ─── Landlord settings API ───────────────────────────────────────────────────

it('landlord can save a resend api key, which is stored encrypted and never returned', function () {
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->putJson('/api/v1/landlord/platform-settings/email', [
            'driver' => 'resend',
            'api_key' => 're_live_abc123',
            'from_address' => 'mail@lendr.app',
            'from_name' => 'LENDR',
        ])
        ->assertOk()
        ->assertJsonPath('data.is_active', true);

    $config = PlatformEmailConfig::first();
    expect($config->driver)->toBe('resend')
        ->and($config->api_key)->toBe('re_live_abc123')
        ->and($config->is_active)->toBeTrue()
        ->and(DB::table('platform_email_configs')->value('api_key'))->not->toContain('re_live_abc123');

    $this->actingAs($landlord, 'sanctum')
        ->getJson('/api/v1/landlord/platform-settings/email')
        ->assertOk()
        ->assertJsonPath('data.driver', 'resend')
        ->assertJsonPath('data.has_api_key', true)
        ->assertJsonMissingPath('data.api_key');
});

it('keeps the stored resend key when saved with a blank key', function () {
    activeResendConfig();
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->putJson('/api/v1/landlord/platform-settings/email', [
            'driver' => 'resend',
            'from_address' => 'hello@lendr.app',
        ])
        ->assertOk();

    expect(PlatformEmailConfig::first()->api_key)->toBe('re_test_123')
        ->and(PlatformEmailConfig::first()->from_address)->toBe('hello@lendr.app');
});

it('rejects switching to resend without an api key and persists nothing', function () {
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->putJson('/api/v1/landlord/platform-settings/email', ['driver' => 'resend'])
        ->assertStatus(422);

    expect(PlatformEmailConfig::count())->toBe(0);
});

it('rejects a malformed resend api key', function () {
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->putJson('/api/v1/landlord/platform-settings/email', ['driver' => 'resend', 'api_key' => 'not-a-key'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('api_key');
});

it('sends the landlord test email through resend', function () {
    fakeResend();
    activeResendConfig();
    $landlord = LandlordUser::factory()->create();

    $this->actingAs($landlord, 'sanctum')
        ->postJson('/api/v1/landlord/platform-settings/email/test', ['to' => 'ops@example.com'])
        ->assertOk();

    Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://api.resend.com/emails'
        && $r->hasHeader('Authorization', 'Bearer re_test_123')
        && $r['to'] === ['ops@example.com']
        && $r['from'] === '"LENDR" <mail@lendr.app>');
});

// ─── Delivery + sender name ──────────────────────────────────────────────────

it('sends system emails as LENDR when no tenant is active', function () {
    fakeResend();
    activeResendConfig();

    Mail::mailer('platform')->raw('Trial ending', fn ($m) => $m->to('admin@mfi.test')->subject('Trial'));

    Http::assertSent(fn (HttpRequest $r) => $r['from'] === '"LENDR" <mail@lendr.app>'
        && $r['subject'] === 'Trial'
        && $r['text'] === 'Trial ending');
});

it('sends tenant emails with the workspace slug as the sender name', function () {
    fakeResend();
    activeResendConfig();
    actAsTenantContext('vozara');

    Mail::mailer('platform')->raw('Your loan was approved', fn ($m) => $m->to('borrower@test.zm')->subject('Approved'));

    Http::assertSent(fn (HttpRequest $r) => $r['from'] === '"vozara" <mail@lendr.app>');
});

it('applies the tenant slug through TenantMailService too', function () {
    fakeResend();
    activeResendConfig();
    actAsTenantContext('kwacha-loans');

    app(\App\Services\Mail\TenantMailService::class)->raw('borrower@test.zm', 'Statement', 'See attached');

    Http::assertSent(fn (HttpRequest $r) => $r['from'] === '"kwacha-loans" <mail@lendr.app>');
});

it('leaves an explicit From address untouched', function () {
    fakeResend();
    activeResendConfig();
    actAsTenantContext('vozara');

    Mail::mailer('platform')->raw('Hi', fn ($m) => $m->from('loans@vozara.co.zm', 'Vozara Finance')->to('a@b.zm')->subject('Hi'));

    Http::assertSent(fn (HttpRequest $r) => $r['from'] === '"Vozara Finance" <loans@vozara.co.zm>');
});

it('picks up a newly saved key without rebuilding the mailer', function () {
    fakeResend();
    $mailer = Mail::mailer('platform'); // resolved before any config exists (e.g. a long-running worker)

    activeResendConfig(['api_key' => 're_saved_later']);
    $mailer->raw('Hi', fn ($m) => $m->to('a@b.zm')->subject('Hi'));

    Http::assertSent(fn (HttpRequest $r) => $r->hasHeader('Authorization', 'Bearer re_saved_later'));
});

it('falls back to the env mailer when no platform config is active', function () {
    Http::fake();
    config(['mail.mailers.platform.fallback' => 'log']);

    Mail::mailer('platform')->raw('Hi', fn ($m) => $m->to('a@b.zm')->subject('Hi'));

    Http::assertNothingSent();
});

it('surfaces resend api errors instead of failing silently', function () {
    Http::fake(['api.resend.com/*' => Http::response(['message' => 'The lendr.app domain is not verified.'], 403)]);
    activeResendConfig();

    expect(fn () => Mail::mailer('platform')->raw('Hi', fn ($m) => $m->to('a@b.zm')->subject('Hi')))
        ->toThrow(RuntimeException::class, 'domain is not verified');
});
