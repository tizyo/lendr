<?php

use App\Enums\UserRole;
use App\Mail\BroadcastMail;
use App\Mail\StaffInvitationMail;
use App\Mail\TenantVerificationMail;
use App\Models\Landlord\EmailLog;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlatformEmailConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

/*
 * The landlord's tenant page: every email to a workspace's owner and staff is
 * logged with its delivery status and can be sent again; support can write to
 * them; staff sign-ins and what they did next are visible.
 */

beforeEach(function () {
    config(['tenancy.bootstrappers' => [], 'app.url' => 'https://lendr.test']);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(function () {
        Tenant::query()->each(function ($tenant) {
            $tenant->domains()->delete();
            $tenant->delete();
        });
    });
    EmailLog::query()->delete();
    PlatformEmailConfig::query()->delete();
});

function commsTenant(array $extra = []): Tenant
{
    return Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'comms-'.uniqid(), 'name' => 'Mwila Microfinance', 'slug' => 'mwila-'.uniqid(),
        'plan' => 'starter', 'status' => 'active', 'currency' => 'ZMW', 'timezone' => 'Africa/Lusaka',
        'admin_email' => 'owner@mwila.test', 'email_verified_at' => now(), ...$extra,
    ]));
}

function commsLandlord()
{
    app('auth')->forgetGuards();
    session()->flush();
    $landlord = LandlordUser::factory()->create(['is_active' => true, 'email' => 'support-'.uniqid().'@lendr.test']);

    return test()->flushHeaders()->withToken($landlord->createToken('landlord-session')->plainTextToken);
}

/** Sending through Resend: POST /emails hands back an id, GET /emails/{id} the last event. */
function fakeResendDelivery(string $lastEvent = 'delivered', int $sendStatus = 200): string
{
    $id = (string) \Illuminate\Support\Str::uuid();
    config(['mail.default' => 'platform']);
    PlatformEmailConfig::create(['driver' => 'resend', 'api_key' => 're_test_key', 'from_address' => 'hello@lendr.test', 'from_name' => 'LENDR', 'is_active' => true]);

    Http::fake([
        'api.resend.com/emails/*' => Http::response(['object' => 'email', 'id' => $id, 'last_event' => $lastEvent]),
        'api.resend.com/emails' => $sendStatus === 200
            ? Http::response(['id' => $id])
            : Http::response(['message' => 'The to address is invalid.'], $sendStatus),
    ]);

    return $id;
}

// ─── What gets logged ────────────────────────────────────────────────────────

it('logs mail to a workspace\'s owner and staff with its content, but never its borrowers\' mail', function () {
    $tenant = commsTenant();
    $staff = User::factory()->create(['email' => 'officer@mwila.test', 'role' => UserRole::LoanOfficer]);

    // Platform mail to the owner (no workspace context): matched by its $tenant.
    Mail::to('owner@mwila.test')->send(new TenantVerificationMail($tenant, 'https://lendr.test/onboarding/verify/abc'));

    tenancy()->initialize($tenant);
    Mail::to($staff->email)->send(new StaffInvitationMail($staff, 'https://lendr.test/invite/xyz', $tenant->name));
    Mail::to('borrower@example.test')->send(new BroadcastMail('Chanda', 'Your statement', 'Hello'));
    tenancy()->end();

    $logs = EmailLog::where('tenant_id', $tenant->id)->orderBy('id')->get();
    expect($logs)->toHaveCount(2)
        ->and($logs[0]->mailable)->toBe('TenantVerificationMail')
        ->and($logs[0]->context)->toBe('platform')
        ->and($logs[0]->status)->toBe('sent')
        ->and($logs[0]->html)->toContain('onboarding/verify/abc')
        ->and($logs[1]->to_email)->toBe('officer@mwila.test')
        ->and($logs[1]->context)->toBe('tenant')
        ->and(EmailLog::where('to_email', 'borrower@example.test')->exists())->toBeFalse();
});

it('never keeps a password reset link, and says so instead of resending it', function () {
    $tenant = commsTenant();
    $staff = User::factory()->create(['email' => 'reset@mwila.test']);

    tenancy()->initialize($tenant);
    $staff->notify(new ResetPasswordNotification('secret-token'));
    tenancy()->end();

    $log = EmailLog::where('tenant_id', $tenant->id)->sole();
    expect($log->html)->toBeNull()->and($log->resendable)->toBeFalse();

    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$tenant->id, $log->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Password reset links are never kept'));
});

// ─── Delivery status ─────────────────────────────────────────────────────────

it('records Resend\'s id and shows whether the email was delivered', function () {
    $tenant = commsTenant();
    $id = fakeResendDelivery('delivered');

    commsLandlord()->postJson(route('landlord.tenants.emails.send', $tenant->id), [
        'recipients' => ['owner@mwila.test'],
        'subject' => 'Checking in',
        'body' => "How is it going?\nReply any time.",
    ])->assertOk()->assertJsonPath('data.sent', ['owner@mwila.test']);

    $log = EmailLog::where('tenant_id', $tenant->id)->sole();
    expect($log->provider)->toBe('resend')
        ->and($log->provider_id)->toBe($id)
        ->and($log->status)->toBe('sent')
        ->and($log->mailable)->toBe('PlatformMessageMail')
        ->and($log->sent_by)->toStartWith('support-');

    // Resend is asked at most once a minute per email.
    $log->forceFill(['status_checked_at' => null])->save();
    commsLandlord()->postJson(route('landlord.tenants.emails.refresh', $tenant->id))
        ->assertOk()
        ->assertJsonPath('data.0.status', 'delivered')
        ->assertJsonPath('data.0.status_label', 'Delivered');

    Http::assertSent(fn ($r) => $r->method() === 'GET' && str_ends_with($r->url(), '/emails/'.$id));
});

it('marks an email Resend refused as failed, with the reason', function () {
    $tenant = commsTenant();
    fakeResendDelivery(sendStatus: 422);

    commsLandlord()->postJson(route('landlord.tenants.emails.send', $tenant->id), [
        'recipients' => ['owner@mwila.test'], 'subject' => 'Hello', 'body' => 'Hi there',
    ])->assertStatus(502);

    $log = EmailLog::where('tenant_id', $tenant->id)->sole();
    expect($log->status)->toBe('failed')->and($log->error)->toContain('The to address is invalid.');
});

it('shows a message stuck in sending as failed', function () {
    $log = EmailLog::create(['tenant_id' => 'x', 'to_email' => 'a@b.test', 'status' => 'sending']);
    $log->forceFill(['created_at' => now()->subMinutes(5)])->save();

    expect($log->fresh()->displayStatus())->toBe('failed');
});

// ─── Sending again ───────────────────────────────────────────────────────────

it('sends a logged email again exactly as it went, to a corrected address if given', function () {
    $tenant = commsTenant();
    Mail::to('owner@mwila.test')->send(new \App\Mail\TenantWelcomeMail($tenant, 'https://lendr.test/login'));
    $original = EmailLog::where('tenant_id', $tenant->id)->sole();

    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$tenant->id, $original->id]), ['to' => 'owner.real@mwila.test'])
        ->assertOk()
        ->assertJsonPath('message', 'Email sent again to owner.real@mwila.test.');

    $copy = EmailLog::where('resent_from_id', $original->id)->sole();
    expect($copy->to_email)->toBe('owner.real@mwila.test')
        ->and($copy->subject)->toBe($original->subject)
        ->and($copy->html)->toBe($original->html)
        ->and($copy->status)->toBe('sent');
});

it('sends a fresh verification link while unverified, and refuses once verified', function () {
    $tenant = commsTenant(['email_verified_at' => null, 'email_verification_token' => 'old-token']);

    commsLandlord()->postJson(route('landlord.tenants.emails.onboarding', $tenant->id))
        ->assertOk()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'Earlier links no longer work'));

    $token = $tenant->fresh()->email_verification_token;
    $log = EmailLog::where('tenant_id', $tenant->id)->sole();
    expect($token)->not->toBe('old-token')
        ->and($log->mailable)->toBe('TenantVerificationMail')
        ->and($log->html)->toContain($token);

    $tenant->update(['email_verified_at' => now()]);
    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$tenant->id, $log->id]))
        ->assertUnprocessable();

    // Once verified, the onboarding button sends the welcome email.
    commsLandlord()->postJson(route('landlord.tenants.emails.onboarding', $tenant->id))
        ->assertOk()->assertJsonPath('message', 'Welcome email sent to owner@mwila.test.');
    expect(EmailLog::where('tenant_id', $tenant->id)->latest('id')->first()->mailable)->toBe('TenantWelcomeMail');
});

it('will not resend an invitation that was already accepted', function () {
    $tenant = commsTenant();
    $staff = User::factory()->create(['email' => 'new@mwila.test', 'invitation_token' => 'tok', 'is_active' => false]);

    tenancy()->initialize($tenant);
    Mail::to($staff->email)->send(new StaffInvitationMail($staff, 'https://lendr.test/invite/tok', $tenant->name));
    tenancy()->end();
    $log = EmailLog::where('tenant_id', $tenant->id)->sole();

    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$tenant->id, $log->id]))->assertOk();

    $staff->forceFill(['invitation_token' => null, 'is_active' => true])->save();
    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$tenant->id, $log->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'already been accepted'));
});

it('keeps one workspace\'s emails out of another\'s page', function () {
    $mine = commsTenant();
    $other = commsTenant(['admin_email' => 'boss@other.test']);
    Mail::to('boss@other.test')->send(new \App\Mail\TenantWelcomeMail($other, 'https://lendr.test/login'));
    $log = EmailLog::where('tenant_id', $other->id)->sole();

    commsLandlord()->getJson(route('landlord.tenants.emails.index', $mine->id))->assertOk()->assertJsonCount(0, 'data');
    commsLandlord()->getJson(route('landlord.tenants.emails.show', [$mine->id, $log->id]))->assertNotFound();
    commsLandlord()->postJson(route('landlord.tenants.emails.resend', [$mine->id, $log->id]))->assertNotFound();
});

// ─── Writing to a workspace ──────────────────────────────────────────────────

it('emails each chosen person by name, with an optional button, and notes it in their audit log', function () {
    $tenant = commsTenant();
    User::factory()->create(['email' => 'jane@mwila.test', 'name' => 'Jane Banda']);

    commsLandlord()->postJson(route('landlord.tenants.emails.send', $tenant->id), [
        'recipients' => ['jane@mwila.test', 'accounts@mwila.test'],
        'subject' => 'Your trial ends soon',
        'body' => 'Please choose a plan.',
        'button_label' => 'Choose a plan',
        'button_url' => 'https://lendr.test/billing',
    ])->assertOk()->assertJsonPath('data.sent', ['jane@mwila.test', 'accounts@mwila.test']);

    $jane = EmailLog::where('to_email', 'jane@mwila.test')->sole();
    expect($jane->html)->toContain('Hi Jane,')->toContain('https://lendr.test/billing')->toContain('Please choose a plan.')
        ->and(EmailLog::where('to_email', 'accounts@mwila.test')->sole()->html)->not->toContain('Hi ');

    expect(Activity::where('log_name', 'support')->where('description', 'like', '%Your trial ends soon%')->exists())->toBeTrue();
});

it('checks what support writes before sending', function () {
    $tenant = commsTenant();

    commsLandlord()->postJson(route('landlord.tenants.emails.send', $tenant->id), [
        'recipients' => ['not-an-email'], 'subject' => '', 'body' => 'x', 'button_label' => 'Go',
    ])->assertUnprocessable()->assertJsonValidationErrors(['recipients.0', 'subject', 'button_url']);

    expect(EmailLog::count())->toBe(0);
});

// ─── Sign-ins and activity ───────────────────────────────────────────────────

it('records a staff sign-in over the API, which used to be silently dropped', function () {
    $user = User::factory()->create(['email' => 'api@mwila.test', 'password' => 'Secret@123', 'is_active' => true]);

    $this->postJson(route('api.v1.auth.login'), ['login' => $user->email, 'password' => 'Secret@123'])->assertOk();

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(Activity::where('log_name', 'auth')->where('causer_id', $user->id)->value('description'))->toBe('Signed in');
});

it('records the screens staff open, once per stretch, and keeps them out of the workspace audit log', function () {
    $tenant = commsTenant(['plan' => 'growth']);
    $admin = actingAsAdmin();
    $admin->recordSignIn('web');
    tenancy()->initialize($tenant);

    $this->actingAs($admin)->get(route('borrowers.index'))->assertOk();
    $this->actingAs($admin)->get(route('borrowers.index'))->assertOk();

    $visits = Activity::where('log_name', 'navigation')->where('causer_id', $admin->id)->get();
    expect($visits)->toHaveCount(1)
        ->and($visits[0]->description)->toBe('borrowers.index')
        ->and($admin->fresh()->last_seen_at)->not->toBeNull();

    $this->actingAs($admin)->getJson(route('api.v1.audit-log.index'))->assertOk()
        ->assertJsonMissing(['description' => 'borrowers.index']);
    tenancy()->end();

    $feed = commsLandlord()->getJson(route('landlord.tenants.activity', [$tenant->id, 'user_id' => $admin->id]))->assertOk()->json('data');
    expect(collect($feed)->pluck('label')->all())->toContain('Opened Borrowers', 'Signed in')
        ->and(collect($feed)->firstWhere('kind', 'auth')['detail'])->toContain('Web');

    commsLandlord()->getJson(route('landlord.tenants.overview', $tenant->id))->assertOk()
        ->assertJsonPath('data.stats.last_login.user_id', $admin->id);
    commsLandlord()->getJson(route('landlord.tenants.users.index', $tenant->id))->assertOk()
        ->assertJsonPath('data.0.last_login_at', fn ($v) => $v !== null);
});

it('prunes old screen visits but keeps the audit trail', function () {
    $user = User::factory()->create();
    activity('navigation')->causedBy($user)->log('loans.index');
    activity('auth')->causedBy($user)->log('Signed in');
    Activity::query()->update(['created_at' => now()->subDays(120)]);

    $this->artisan('lendr:prune-navigation')->assertSuccessful();

    expect(Activity::where('log_name', 'navigation')->count())->toBe(0)
        ->and(Activity::where('log_name', 'auth')->count())->toBe(1);
});
