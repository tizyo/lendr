<?php

use App\Jobs\ProvisionTenantDatabase;
use App\Models\Landlord\EmailLog;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/*
 * Company names are stored in capitals and workspace IDs as 3–10 letters/digits,
 * however a workspace is created; the landlord dashboard reports on every
 * workspace; and mail goes through Platform Settings → Email whatever MAIL_MAILER says.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => []]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(fn () => Tenant::query()->each(function ($t) {
        $t->domains()->delete();
        $t->delete();
    }));
    EmailLog::query()->delete();
    PlatformEmailConfig::query()->delete();
});

function namingTenant(array $attrs = []): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => (string) \Illuminate\Support\Str::uuid(), 'name' => 'Kafue Credit', 'slug' => 'k'.substr(md5(uniqid('', true)), 0, 9),
        'plan' => 'starter', 'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka',
        'email_verified_at' => now(), ...$attrs,
    ]));
}

// ─── Workspace names and IDs ────────────────────────────────────────────────

it('makes a landlord-created workspace\'s ID from its name, in capitals, and keeps IDs unique', function () {
    Bus::fake([ProvisionTenantDatabase::class]);
    app('auth')->forgetGuards();
    $this->withToken(LandlordUser::factory()->create(['is_active' => true])->createToken('landlord-session')->plainTextToken);

    $this->postJson('/api/v1/landlord/tenants', ['name' => 'Lungwe Loans & Co.', 'plan' => 'starter'])
        ->assertCreated()->assertJsonPath('data.name', 'LUNGWE LOANS & CO.');
    $this->postJson('/api/v1/landlord/tenants', ['name' => 'Lungwe Loans Two', 'plan' => 'starter'])->assertCreated();

    expect(Tenant::orderBy('created_at')->orderBy('slug')->pluck('slug')->all())->toEqualCanonicalizing(['lungweloan', 'lungweloa2']);
});

it('takes the landlord\'s own workspace ID when it follows the rule', function () {
    Bus::fake([ProvisionTenantDatabase::class]);
    app('auth')->forgetGuards();
    $this->withToken(LandlordUser::factory()->create(['is_active' => true])->createToken('landlord-session')->plainTextToken);

    $this->postJson('/api/v1/landlord/tenants', ['name' => 'Chipata Cash', 'slug' => 'chip-ata', 'plan' => 'starter'])
        ->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'The workspace ID must be 3 to 10 letters or numbers, with no spaces or symbols.']);

    $this->postJson('/api/v1/landlord/tenants', ['name' => 'Chipata Cash', 'slug' => 'CHIPATA', 'plan' => 'starter'])->assertCreated();
    expect(Tenant::sole()->slug)->toBe('chipata');

    $this->postJson('/api/v1/landlord/tenants', ['name' => 'Other', 'slug' => 'chipata', 'plan' => 'starter'])
        ->assertUnprocessable()->assertJsonValidationErrors(['slug' => 'This workspace ID is already taken.']);
});

it('always finds a valid workspace ID, even for odd names', function () {
    expect(Tenant::availableSlug('AB'))->toBe('ab0')
        ->and(Tenant::availableSlug('!!!'))->toBe('workspace')
        ->and(Tenant::availableSlug('Mwansa Ñkonde Microfinance Ltd'))->toBe('mwansankon')
        ->and(Tenant::availableSlug('Mwansa Ñkonde Microfinance Ltd'))->toMatch(Tenant::SLUG_PATTERN);
});

it('stores the workspace\'s own company name in capitals from settings and branding', function () {
    $admin = actingAsAdmin();
    Sanctum::actingAs($admin);

    $this->putJson(route('api.v1.branding.update'), ['company_name' => 'kafue credit ltd'])->assertOk();
    expect(DB::table('settings')->where('key', 'company_name')->value('value'))->toBe('KAFUE CREDIT LTD');
});

it('brings existing names into line', function () {
    $tenant = namingTenant();
    DB::table('tenants')->where('id', $tenant->id)->update(['name' => 'kuka loans', 'admin_email' => 'Boss@Kuka.ZM']);
    DB::table('settings')->updateOrInsert(['key' => 'company_name'], ['value' => 'kuka loans']);

    (require database_path('migrations/2026_11_05_120000_uppercase_tenant_names.php'))->up();
    (require database_path('migrations/tenant/2026_11_05_120000_uppercase_company_name_setting.php'))->up();

    expect(DB::table('tenants')->where('id', $tenant->id)->value('name'))->toBe('KUKA LOANS')
        ->and(DB::table('tenants')->where('id', $tenant->id)->value('admin_email'))->toBe('boss@kuka.zm')
        ->and(DB::table('settings')->where('key', 'company_name')->value('value'))->toBe('KUKA LOANS');
});

// ─── Mail goes through Platform Settings ────────────────────────────────────

it('sends through the provider saved in Platform Settings even when MAIL_MAILER says otherwise', function () {
    // Production's .env said smtp (127.0.0.1:1025) and every verification email failed.
    expect(config('mail.default'))->toBe('platform');

    PlatformEmailConfig::create(['driver' => 'resend', 'api_key' => 're_live_key', 'from_address' => 'hello@lendr.test', 'is_active' => true]);
    Http::fake(['api.resend.com/emails' => Http::response(['id' => (string) \Illuminate\Support\Str::uuid()])]);

    Mail::to('owner@kafue.test')->send(new \App\Mail\TenantWelcomeMail(namingTenant(['admin_email' => 'owner@kafue.test']), 'https://lendr.test/portal/login'));

    Http::assertSent(fn ($r) => $r->url() === 'https://api.resend.com/emails' && $r['to'] === ['owner@kafue.test']);
});

// ─── The landlord dashboard ─────────────────────────────────────────────────

it('reports on every workspace, with what needs attention', function () {
    namingTenant(['name' => 'Ndola Funds', 'status' => 'trial', 'trial_ends_at' => now()->addDays(3)]);
    namingTenant(['name' => 'Kitwe Kash', 'email_verified_at' => null, 'status' => 'trial', 'trial_ends_at' => now()->addDays(10)]);
    namingTenant(['name' => 'Mansa Money', 'status' => 'suspended']);
    Borrower::factory()->count(3)->create(); // tests share one database: every workspace "has" them
    EmailLog::create(['tenant_id' => null, 'to_email' => 'x@y.test', 'subject' => 'Welcome', 'status' => 'bounced', 'provider' => 'resend', 'provider_id' => (string) \Illuminate\Support\Str::uuid()]);
    EmailLog::create(['tenant_id' => null, 'to_email' => 'z@y.test', 'subject' => 'Hello', 'status' => 'delivered', 'provider' => 'resend', 'provider_id' => (string) \Illuminate\Support\Str::uuid()]);

    $data = $this->actingAs(LandlordUser::factory()->create(), 'sanctum')
        ->getJson('/api/v1/landlord/stats?fresh=1')->assertOk()->json('data');

    expect($data['overview']['workspaces'])->toBe(3)
        ->and($data['overview']['unverified'])->toBe(1)
        ->and($data['monthly'])->toHaveCount(12)
        ->and(end($data['monthly'])['month'])->toBe(now()->format('Y-m'))
        ->and(end($data['monthly'])['signups'])->toBe(3)
        ->and($data['usage']['workspaces'])->toHaveCount(3)
        ->and($data['usage']['workspaces'][0]['borrowers'])->toBe(3)
        ->and($data['usage']['workspaces'][0]['reachable'])->toBeTrue()
        ->and($data['email'])->toMatchArray(['total' => 2, 'delivered' => 1, 'failed' => 1])
        ->and($data['recent_signups'])->toHaveCount(3);

    $attention = collect($data['attention']);
    expect($attention->pluck('type')->all())->toBe(['suspended', 'unverified', 'email_failed', 'trial_ending'])
        ->and($attention->firstWhere('type', 'trial_ending')['message'])->toBe('Trial ends in 3 days.')
        ->and($attention->firstWhere('type', 'unverified')['tenant_name'])->toBe('KITWE KASH');
});
