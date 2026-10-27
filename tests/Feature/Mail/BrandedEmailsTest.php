<?php

use App\Mail\BroadcastMail;
use App\Mail\LoanEventMail;
use App\Mail\StaffWelcome;
use App\Mail\TenantVerificationMail;
use App\Mail\TenantWelcomeMail;
use App\Models\Landlord\PlatformBranding;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Notifications\ResetPasswordNotification;
use App\Services\Mail\EmailBrand;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

// ─── Helpers ─────────────────────────────────────────────────────────────────

afterEach(function () {
    tenancy()->tenant = null;
    tenancy()->initialized = false;
});

function brandedTenant(): Tenant
{
    return (new Tenant)->forceFill([
        'id' => 'tenant-brand-test',
        'name' => 'Vozara Finance',
        'slug' => 'vozara',
        'plan' => 'starter',
        'admin_email' => 'owner@vozara.test',
    ]);
}

/** Pretend we're inside a tenant (tests share one DB, so `settings` is reachable). */
function enterTenantContext(array $settings = []): Tenant
{
    foreach ($settings as $key => $value) {
        DB::table('settings')->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }

    $tenant = brandedTenant();
    tenancy()->tenant = $tenant;
    tenancy()->initialized = true;

    return $tenant;
}

function staffMember(): User
{
    return User::factory()->create(['name' => 'Mwila Banda', 'email' => 'mwila@vozara.test']);
}

// ─── Platform (system) emails ────────────────────────────────────────────────

it('brands system emails with the platform name and colour', function () {
    PlatformBranding::current()->update(['company_name' => 'LENDR', 'primary_color' => '#0F766E']);

    $html = (new TenantVerificationMail(brandedTenant(), 'https://lendr.test/verify/abc'))->render();

    expect($html)
        ->toContain('The LENDR team')
        ->toContain('#0F766E')
        ->toContain('https://lendr.test/verify/abc')
        ->not->toContain('via LENDR'); // the "sent via" line is for tenant emails only
});

it('renders the new workspace welcome email', function () {
    $html = (new TenantWelcomeMail(brandedTenant(), 'https://app.lendr.test/login'))->render();

    expect($html)
        ->toContain('Vozara Finance')
        ->toContain('https://app.lendr.test/login')
        ->toContain('Get set up in four steps')
        ->toContain('vozara'); // shared-portal tenants sign in with their workspace ID
});

// ─── Tenant emails ───────────────────────────────────────────────────────────

it('brands tenant emails with the tenant name, colour and logo', function () {
    enterTenantContext([
        'company_name' => 'Vozara Finance',
        'primary_color' => '#7C3AED',
        'company_logo_path' => 'branding/logos/vozara.png',
        'support_phone' => '+260 97 123 4567',
    ]);

    $html = (new StaffWelcome(staffMember(), 'Tmp#8842'))->render();

    expect($html)
        ->toContain('The Vozara Finance team')
        ->toContain('#7C3AED')
        ->toContain('/email-assets/tenant-brand-test/logo')
        ->toContain('+260 97 123 4567')
        ->toContain('Sent by Vozara Finance via LENDR')
        ->toContain('Tmp#8842');
});

it('prefers the dedicated email header colour over the primary colour', function () {
    enterTenantContext(['primary_color' => '#7C3AED', 'email_header_color' => '#B91C1C']);

    expect(app(EmailBrand::class)->current()['primary'])->toBe('#B91C1C');
});

it('falls back to the tenant name when the branding name is still the LENDR placeholder', function () {
    enterTenantContext(['company_name' => 'LENDR']);

    expect(app(EmailBrand::class)->current()['name'])->toBe('Vozara Finance');
});

it('uses dark button text on pale brand colours so buttons stay readable', function () {
    enterTenantContext(['primary_color' => '#FDE047']);

    $brand = app(EmailBrand::class)->current();

    expect($brand['on_primary'])->toBe('#111827')
        ->and($brand['accent'])->not->toBe('#FDE047'); // links darkened for contrast on white
});

it('skips SVG logos, which Gmail and Outlook cannot display', function () {
    enterTenantContext(['company_logo_path' => 'branding/logos/vozara.svg']);

    expect(app(EmailBrand::class)->current()['logo_url'])->toBeNull();
});

it('sends a branded password reset email in tenant context', function () {
    Notification::fake();
    enterTenantContext(['company_name' => 'Vozara Finance', 'primary_color' => '#7C3AED']);
    $user = staffMember();

    $user->sendPasswordResetNotification('reset-token-123');

    Notification::assertSentTo($user, ResetPasswordNotification::class, function ($notification) use ($user) {
        $mail = $notification->toMail($user);
        $html = (string) $mail->render();

        return $mail->subject === 'Reset your Vozara Finance password'
            && str_contains($html, 'reset-token-123')
            && str_contains($html, '#7C3AED')
            && str_contains($html, 'mwila@vozara.test');
    });
});

it('keeps the shared-portal reset URL override working', function () {
    enterTenantContext();
    $user = staffMember();

    ResetPasswordNotification::createUrlUsing(fn ($n, $token) => "https://app.lendr.test/reset/{$token}?workspace=vozara");

    try {
        $html = (string) (new ResetPasswordNotification('tok-9'))->toMail($user)->render();
    } finally {
        ResetPasswordNotification::createUrlUsing(null);
    }

    expect($html)->toContain('https://app.lendr.test/reset/tok-9?workspace=vozara');
});

it('renders broadcast emails (previously a fatal readonly-property error)', function () {
    enterTenantContext();

    $html = (new BroadcastMail('Chanda', 'Office closed Friday', "We're closed on Friday.\n\nMobile money still works."))->render();

    expect($html)->toContain('Office closed Friday')->toContain('Mobile money still works.');
});

it('uses the danger button for overdue loan notices', function () {
    enterTenantContext();

    $html = (new LoanEventMail('Chanda', 'c@x.test', 'overdue', 'LN-42', ['due_date' => '30 Oct 2026']))->render();

    expect($html)->toContain('Payment Overdue')->toContain('#D92D20')->toContain('30 Oct 2026');
});

// ─── Logo endpoint ───────────────────────────────────────────────────────────

it('returns 404 for an unknown tenant logo', function () {
    $this->get('/email-assets/does-not-exist/logo')->assertNotFound();
});
