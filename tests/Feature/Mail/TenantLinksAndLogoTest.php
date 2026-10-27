<?php

use App\Mail\LoanEventMail;
use App\Mail\StaffWelcome;
use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Services\Mail\EmailBrand;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    config(['app.url' => 'https://lendr.test']);
});

afterEach(function () {
    tenancy()->tenant = null;
    tenancy()->initialized = false;
    Tenant::withoutEvents(fn () => Tenant::query()->each(fn ($t) => $t->domains()->delete() && $t->delete()));
});

/** A saved tenant, without provisioning its database (TenantCreated runs synchronously). */
function linkTenant(string $plan, ?string $domain = null, string $slug = 'vozara'): Tenant
{
    $slug = Tenant::where('slug', $slug)->exists() ? $slug.'-'.uniqid() : $slug;

    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'links-'.uniqid(),
        'name' => 'Vozara Finance',
        'slug' => $slug,
        'plan' => $plan,
        'status' => 'active',
    ]));

    if ($domain) {
        $tenant->domains()->create(['domain' => $domain]);
    }

    return $tenant;
}

function planWithPwa(string $plan, bool $pwa): void
{
    PlanConfig::updateOrCreate(['plan' => $plan], ['label' => ucfirst($plan), 'features' => ['pwa' => $pwa]]);
}

function inTenant(Tenant $tenant): void
{
    tenancy()->tenant = $tenant;
    tenancy()->initialized = true;
}

// ─── Tenant URLs ─────────────────────────────────────────────────────────────

it('sends shared-portal staff to the portal login', function () {
    expect(linkTenant('starter')->loginUrl())->toEndWith('/portal/login');
});

it('sends subdomain staff to their own login page, using the APP_URL scheme', function () {
    expect(linkTenant('growth', 'vozara.lendr.test')->loginUrl())->toBe('https://vozara.lendr.test/login');
});

it('only links the borrower portal on a subdomain with the pwa feature', function () {
    planWithPwa('growth', true);
    planWithPwa('starter', false);

    expect(linkTenant('growth', 'vozara.lendr.test')->borrowerPortalUrl('/loans'))->toBe('https://vozara.lendr.test/app/loans')
        ->and(linkTenant('growth')->borrowerPortalUrl('/loans'))->toBeNull()
        ->and(linkTenant('starter', 'other.lendr.test')->borrowerPortalUrl('/loans'))->toBeNull();
});

// ─── Links inside emails ─────────────────────────────────────────────────────

it('links loan notices to the tenant borrower portal, not the central domain', function () {
    planWithPwa('growth', true);
    inTenant(linkTenant('growth', 'vozara.lendr.test'));

    $html = (new LoanEventMail('Chanda', 'c@x.test', 'overdue', 'LN-42', ['due_date' => '30 Oct 2026']))->render();

    expect($html)->toContain('https://vozara.lendr.test/app/loans')
        ->toContain('#D92D20') // overdue → danger button
        ->not->toContain('https://lendr.test/app/loans');
});

it('gives shared-portal staff the portal login link and their workspace ID', function () {
    inTenant(linkTenant('starter'));

    $html = (new StaffWelcome(User::factory()->create(['name' => 'Mwila Banda']), 'Tmp#1'))->render();

    expect($html)->toContain('/portal/login')->toContain('Your workspace ID')->toContain('vozara');
});

it('reads tenant contact details saved from Settings → General', function () {
    DB::table('settings')->insert([
        ['key' => 'company_phone', 'value' => '+260 211 000000', 'updated_at' => now()],
        ['key' => 'company_email', 'value' => 'info@vozara.test', 'updated_at' => now()],
    ]);
    inTenant(linkTenant('starter'));

    $brand = app(EmailBrand::class)->current();

    expect($brand['phone'])->toBe('+260 211 000000')->and($brand['email'])->toBe('info@vozara.test');
});

// ─── Logo upload (Settings → Branding) ───────────────────────────────────────

it('lets an admin upload, show and remove the tenant logo', function () {
    Storage::fake('public');
    $admin = actingAsAdmin();

    $this->actingAs($admin)
        ->post(route('settings.logo.upload'), ['logo' => UploadedFile::fake()->image('logo.png', 300, 100)])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $path = DB::table('settings')->where('key', 'company_logo_path')->value('value');
    expect($path)->toStartWith('branding/logos/');
    Storage::disk('public')->assertExists($path);

    $this->actingAs($admin)->get(route('settings.index'))
        ->assertInertia(fn ($page) => $page->where('logoUrl', fn ($url) => str_contains((string) $url, $path)));

    $this->actingAs($admin)->delete(route('settings.logo.delete'))->assertRedirect();

    expect(DB::table('settings')->where('key', 'company_logo_path')->exists())->toBeFalse();
    Storage::disk('public')->assertMissing($path);
});

it('replaces the old logo file on re-upload', function () {
    Storage::fake('public');
    $admin = actingAsAdmin();

    $this->actingAs($admin)->post(route('settings.logo.upload'), ['logo' => UploadedFile::fake()->image('a.png')]);
    $first = DB::table('settings')->where('key', 'company_logo_path')->value('value');

    $this->actingAs($admin)->post(route('settings.logo.upload'), ['logo' => UploadedFile::fake()->image('b.png')]);
    $second = DB::table('settings')->where('key', 'company_logo_path')->value('value');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

it('rejects SVG logos', function () {
    Storage::fake('public');

    $this->actingAs(actingAsAdmin())
        ->post(route('settings.logo.upload'), [
            'logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        ])
        ->assertSessionHasErrors('logo');

    expect(DB::table('settings')->where('key', 'company_logo_path')->exists())->toBeFalse();
});

it('uses the same logo URL in the branding API', function () {
    Storage::fake('public');
    $admin = actingAsAdmin();

    $url = $this->actingAs($admin)
        ->postJson(route('api.v1.branding.logo'), ['logo' => UploadedFile::fake()->image('logo.png')])
        ->assertOk()
        ->json('data.url');

    $this->getJson(route('api.v1.branding.show'))->assertJsonPath('data.logo_url', $url);
});
