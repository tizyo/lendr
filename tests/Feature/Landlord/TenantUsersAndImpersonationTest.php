<?php

use App\Enums\UserRole;
use App\Http\Middleware\InitializeTenancy;
use App\Mail\StaffInvitationMail;
use App\Models\Landlord\GhostUser;
use App\Models\Landlord\ImpersonationSession;
use App\Models\Landlord\LandlordUser;
use App\Models\Landlord\PlanConfig;
use App\Models\Landlord\Tenant;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Spatie\Activitylog\Models\Activity;

// ─── Helpers ─────────────────────────────────────────────────────────────────

beforeEach(function () {
    // Tests share one database, so tenancy can initialize for real without switching it.
    config(['tenancy.bootstrappers' => [], 'app.url' => 'https://lendr.test']);
    PlanConfig::updateOrCreate(['plan' => 'starter'], ['label' => 'Starter', 'features' => ['max_users' => 50]]);
    PlanConfig::updateOrCreate(['plan' => 'growth'], ['label' => 'Growth', 'features' => ['max_users' => 50]]);
});

afterEach(function () {
    tenancy()->end();
    Tenant::withoutEvents(function () {
        Tenant::query()->each(function ($tenant) {
            $tenant->domains()->delete();
            $tenant->delete();
        });
    });
});

/** A saved tenant, without provisioning its database (TenantCreated runs synchronously). */
function supportTenant(string $plan = 'starter', string $status = 'active', ?string $domain = null): Tenant
{
    $tenant = Tenant::withoutEvents(fn () => Tenant::create([
        'id' => 'support-'.uniqid(),
        'name' => 'Vozara Finance',
        'slug' => 'vozara-'.uniqid(),
        'plan' => $plan,
        'status' => $status,
        'currency' => 'ZMW',
        'timezone' => 'Africa/Lusaka',
    ]));

    if ($domain) {
        $tenant->domains()->create(['domain' => $domain]);
    }

    return $tenant;
}

function supportStaff(array $overrides = []): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true, ...$overrides]);
}

/**
 * Each request gets its own session and auth state in production, but tests share
 * one app. Landlord routes start no session, so theirs must look empty here too.
 */
function asLandlord(?LandlordUser $landlord = null)
{
    app('auth')->forgetGuards();
    session()->flush();
    $landlord ??= LandlordUser::factory()->create(['is_active' => true]);

    return test()->flushHeaders()->withToken($landlord->createToken('landlord-session')->plainTextToken);
}

function asGuestBrowser()
{
    app('auth')->forgetGuards();
    app('auth')->shouldUse('web');

    return test()->flushHeaders();
}

/** Issue a support link and return its path, as the panel's "Start Session" does. */
function supportLink(Tenant $tenant, User $user, string $host = 'localhost'): string
{
    config(['app.url' => "http://{$host}"]);

    $url = asLandlord()
        ->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/impersonate", ['reason' => 'Ticket #142: disbursement screen'])
        ->assertCreated()
        ->json('data.url');

    asGuestBrowser();

    return $url;
}

// ─── Who may use these endpoints ─────────────────────────────────────────────

it('refuses marketplace users on tenant user endpoints', function () {
    $tenant = supportTenant();
    $user = supportStaff();
    $ghost = GhostUser::create(['phone' => '0977123456', 'name' => 'Repo Buyer']);

    $this->withToken($ghost->createToken('marketplace')->plainTextToken);

    $this->getJson("/api/v1/landlord/tenants/{$tenant->id}/users")->assertForbidden();
    $this->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/impersonate", ['reason' => 'Trying my luck'])->assertForbidden();

    expect(ImpersonationSession::count())->toBe(0);
});

it('never reads a browser session on landlord routes', function () {
    // Sanctum checks a stateful session before the bearer token, so a tenant login in
    // the same browser would turn the landlord's API calls into that staff member's.
    $middleware = fn (string $route) => app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($route));

    expect($middleware('landlord.tenants.index'))->not->toContain(EnsureFrontendRequestsAreStateful::class)
        ->and($middleware('landlord.tenants.users.impersonate'))->not->toContain(EnsureFrontendRequestsAreStateful::class)
        ->and($middleware('api.v1.auth.me'))->toContain(EnsureFrontendRequestsAreStateful::class);
});

it('initializes tenancy before authenticating', function () {
    // Otherwise users and tokens are looked up in the central database. A shared test
    // database hides this, so the order itself is what gets checked here.
    $order = function (string $route) {
        $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($route));
        $position = fn (string $needle) => collect($middleware)->search(fn ($m) => is_string($m) && str_starts_with($m, $needle));

        return [$position(InitializeTenancy::class), $position(Authenticate::class)];
    };

    foreach (['dashboard', 'portal.dashboard', 'api.v1.auth.me', 'api.v1.borrowers.index'] as $route) {
        [$tenancy, $auth] = $order($route);

        expect($tenancy)->toBeInt()->and($auth)->toBeInt()->and($tenancy)->toBeLessThan($auth);
    }
});

it('does not carry a staff login across to another tenant\'s subdomain', function () {
    // With SESSION_DOMAIN=.lendr.cc every subdomain shares one cookie, and a session only
    // holds a user id. Tenant B's user with that id must not inherit tenant A's login.
    $a = supportTenant('growth', domain: 'a.lendr.test');
    $a->update(['email_verified_at' => now()]);
    supportTenant('growth', domain: 'b.lendr.test');
    $user = supportStaff(['role' => UserRole::SuperAdmin, 'password' => 'Secret#12345']);

    $this->post('http://a.lendr.test/login', ['email' => $user->email, 'password' => 'Secret#12345'])
        ->assertRedirect('http://a.lendr.test/dashboard');
    $this->get('http://a.lendr.test/staff/profile')->assertOk();

    app('auth')->forgetGuards();
    tenancy()->end();

    $this->get('http://b.lendr.test/staff/profile')->assertRedirect();
    $this->assertGuest('web');
});

it('keeps a staff login on its own subdomain across requests', function () {
    $a = supportTenant('growth', domain: 'a.lendr.test');
    $a->update(['email_verified_at' => now()]);
    $user = supportStaff(['role' => UserRole::SuperAdmin, 'password' => 'Secret#12345']);

    $this->post('http://a.lendr.test/login', ['email' => $user->email, 'password' => 'Secret#12345'])->assertRedirect();

    app('auth')->forgetGuards();

    $this->get('http://a.lendr.test/staff/profile')->assertOk();
});

// ─── Viewing a tenant ────────────────────────────────────────────────────────

it('shows a tenant overview', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    supportStaff();
    supportStaff(['is_active' => false]);
    Borrower::factory()->count(2)->create();

    asLandlord()->getJson("/api/v1/landlord/tenants/{$tenant->id}/overview")
        ->assertOk()
        ->assertJsonPath('data.tenant.name', 'Vozara Finance')
        ->assertJsonPath('data.tenant.domains.0', 'vozara.lendr.test')
        ->assertJsonPath('data.tenant.login_url', 'https://vozara.lendr.test/login')
        ->assertJsonPath('data.stats.staff_total', 2)
        ->assertJsonPath('data.stats.staff_active', 1)
        ->assertJsonPath('data.stats.borrowers', 2)
        ->assertJsonPath('data.stats.active_loans', 0);

    expect(tenancy()->initialized)->toBeFalse();
});

it('lists staff with their status, without secrets', function () {
    $tenant = supportTenant();
    supportStaff(['name' => 'Active Ann']);
    supportStaff(['name' => 'Suspended Sam', 'is_active' => false]);
    supportStaff(['name' => 'Invited Ivy', 'is_active' => false, 'invitation_token' => 'abc']);

    $response = asLandlord()->getJson("/api/v1/landlord/tenants/{$tenant->id}/users")->assertOk();

    expect(collect($response->json('data'))->pluck('status', 'name')->all())->toBe([
        'Active Ann' => 'active',
        'Invited Ivy' => 'invited',
        'Suspended Sam' => 'suspended',
    ]);
    expect($response->getContent())->not->toContain('two_factor_secret')->not->toContain('invitation_token');
});

// ─── Adding users ────────────────────────────────────────────────────────────

it('adds a user by emailing them an invitation', function () {
    Mail::fake();
    $tenant = supportTenant();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users", [
        'name' => 'New Officer',
        'email' => 'new@vozara.test',
        'role' => 'loan_officer',
    ])->assertCreated()->assertJsonPath('data.status', 'invited');

    $user = User::where('email', 'new@vozara.test')->first();
    expect($user->is_active)->toBeFalse()->and($user->invitation_token)->not->toBeNull();

    Mail::assertSent(StaffInvitationMail::class, fn ($mail) => str_contains($mail->invitationUrl, $tenant->id));
    expect(Activity::where('description', 'LENDR support invited New Officer')->exists())->toBeTrue();
});

it('refuses a duplicate email and leaves tenancy clean', function () {
    $tenant = supportTenant();
    supportStaff(['email' => 'taken@vozara.test']);

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users", [
        'name' => 'Dupe',
        'email' => 'taken@vozara.test',
        'role' => 'cashier',
    ])->assertUnprocessable()->assertJsonValidationErrors(['email']);

    expect(tenancy()->initialized)->toBeFalse();
});

it('respects the plan staff limit', function () {
    Mail::fake();
    PlanConfig::where('plan', 'starter')->update(['features' => ['max_users' => 1]]);
    $tenant = supportTenant();
    supportStaff();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users", [
        'name' => 'Over Limit',
        'email' => 'over@vozara.test',
        'role' => 'cashier',
    ])->assertUnprocessable();

    expect(User::where('email', 'over@vozara.test')->exists())->toBeFalse();
    Mail::assertNothingSent();
});

it('still adds the user when the invitation email fails', function () {
    Mail::shouldReceive('mailer')->once()->andThrow(new RuntimeException('provider down'));
    $tenant = supportTenant();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users", [
        'name' => 'Unlucky',
        'email' => 'unlucky@vozara.test',
        'role' => 'cashier',
    ])->assertCreated()->assertJsonPath('message', "User added, but the invitation email failed. Use 'Resend invite' to try again.");

    expect(User::where('email', 'unlucky@vozara.test')->exists())->toBeTrue();
});

it('resends a pending invitation, but not to someone who already accepted', function () {
    Mail::fake();
    $tenant = supportTenant();
    $invited = supportStaff(['is_active' => false, 'invitation_token' => 'abc']);
    $accepted = supportStaff();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$invited->id}/resend-invite")->assertOk();
    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$accepted->id}/resend-invite")->assertUnprocessable();

    Mail::assertSent(StaffInvitationMail::class, 1);
});

it('signs a shared-portal invitee straight in', function () {
    $tenant = supportTenant();
    $user = supportStaff(['is_active' => false, 'invitation_token' => 'portal-invite']);

    $this->post("/invitation/{$tenant->id}/portal-invite", ['password' => 'Accepted#12345', 'password_confirmation' => 'Accepted#12345'])
        ->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user, 'web');
    expect(session('tenant_id'))->toBe($tenant->id)
        ->and(session(InitializeTenancy::SESSION_TENANT_KEY))->toBe($tenant->id);
});

it('sends a subdomain invitee to their own login page', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    $user = supportStaff(['is_active' => false, 'invitation_token' => 'sub-invite']);

    $this->post("/invitation/{$tenant->id}/sub-invite", ['password' => 'Accepted#12345', 'password_confirmation' => 'Accepted#12345'])
        ->assertRedirect('https://vozara.lendr.test/login');

    expect($user->fresh()->is_active)->toBeTrue();
    $this->assertGuest('web');
});

// ─── Suspending users ────────────────────────────────────────────────────────

it('suspending ends the user\'s open session and revokes their tokens', function () {
    $tenant = supportTenant();
    supportStaff(['role' => UserRole::SuperAdmin]);
    $user = supportStaff();
    $token = $user->createToken('device')->plainTextToken;

    // Both work before the suspension.
    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertOk();
    asGuestBrowser()->withSession([Auth::guard('web')->getName() => $user->id])->get('/staff/profile')->assertOk();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($user->fresh()->is_active)->toBeFalse()->and($user->tokens()->count())->toBe(0);
    expect(Activity::where('description', "LENDR support suspended {$user->name}")->exists())->toBeTrue();

    asGuestBrowser()->withToken($token)->getJson(route('api.v1.auth.me'))->assertUnauthorized();
    asGuestBrowser()->withSession([Auth::guard('web')->getName() => $user->id])->get('/staff/profile')->assertRedirect();
});

it('stops a token still held by any user deactivated another way', function () {
    $user = supportStaff();
    $token = $user->createToken('device')->plainTextToken;

    $user->update(['is_active' => false]);

    $this->withToken($token)->getJson(route('api.v1.auth.me'))->assertUnauthorized();
});

it('refuses to suspend the only active super admin', function () {
    $tenant = supportTenant();
    $admin = supportStaff(['role' => UserRole::SuperAdmin]);
    supportStaff(['role' => UserRole::SuperAdmin, 'is_active' => false]);

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$admin->id}/suspend")
        ->assertUnprocessable();

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('reactivates a suspended user', function () {
    $tenant = supportTenant();
    $user = supportStaff(['is_active' => false]);

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/activate")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($user->fresh()->is_active)->toBeTrue();
});

it('will not activate someone who has not accepted their invitation', function () {
    $tenant = supportTenant();
    $user = supportStaff(['is_active' => false, 'invitation_token' => 'abc']);

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/activate")
        ->assertUnprocessable();

    expect($user->fresh()->is_active)->toBeFalse();
});

// ─── Starting a support session ──────────────────────────────────────────────

it('requires a reason to sign in as someone', function () {
    $tenant = supportTenant();
    $user = supportStaff();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$tenant->id}/users/{$user->id}/impersonate", ['reason' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
});

it('refuses inactive users and inactive workspaces', function () {
    $active = supportTenant();
    $suspendedUser = supportStaff(['is_active' => false]);

    asLandlord()->postJson("/api/v1/landlord/tenants/{$active->id}/users/{$suspendedUser->id}/impersonate", ['reason' => 'Ticket #1 help'])
        ->assertUnprocessable();

    $suspendedTenant = supportTenant(status: 'suspended');
    $user = supportStaff();

    asLandlord()->postJson("/api/v1/landlord/tenants/{$suspendedTenant->id}/users/{$user->id}/impersonate", ['reason' => 'Ticket #1 help'])
        ->assertUnprocessable();

    expect(ImpersonationSession::count())->toBe(0);
});

it('stores only a hash of the link token', function () {
    $tenant = supportTenant();
    $url = supportLink($tenant, supportStaff());
    $token = basename($url);

    expect(ImpersonationSession::first()->token_hash)->toBe(hash('sha256', $token))->not->toBe($token);
});

it('points subdomain tenants at their own domain', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');

    expect(supportLink($tenant, supportStaff()))->toStartWith('http://vozara.lendr.test/impersonate/');
});

// ─── Entering through the link ───────────────────────────────────────────────

it('signs in through the link on the shared portal, marking the session', function () {
    $tenant = supportTenant();
    $user = supportStaff();

    $this->get(supportLink($tenant, $user))->assertRedirect(route('portal.dashboard'));

    $this->assertAuthenticatedAs($user, 'web');

    $session = ImpersonationSession::first();
    expect($session->started_at)->not->toBeNull()
        ->and($session->isLive())->toBeTrue()
        ->and($session->token_hash)->toBeNull()
        ->and((int) round(now()->diffInMinutes($session->expires_at)))->toBe(30);

    $this->assertEquals($session->id, session(ImpersonationSession::SESSION_KEY));
    $this->assertEquals($tenant->id, session('tenant_id'));

    $entry = Activity::where('description', "LENDR support signed in as {$user->name}")->first();
    expect($entry->properties['reason'])->toBe('Ticket #142: disbursement screen');
});

it('signs in on the tenant\'s own subdomain', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    $user = supportStaff();

    $this->get(supportLink($tenant, $user))->assertRedirect('http://vozara.lendr.test/dashboard');

    $this->assertAuthenticatedAs($user, 'web');
});

it('only works once', function () {
    $tenant = supportTenant();
    $link = supportLink($tenant, supportStaff());

    $this->get($link)->assertRedirect();

    asGuestBrowser()->get($link)->assertStatus(410);
});

it('expires after a minute', function () {
    $tenant = supportTenant();
    $link = supportLink($tenant, supportStaff());

    $this->travel(ImpersonationSession::LINK_SECONDS + 1)->seconds();

    $this->get($link)->assertStatus(410);

    expect(ImpersonationSession::first()->status())->toBe('unused');
    $this->assertGuest('web');
});

it('rejects the link on another tenant\'s host without spending it', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    supportTenant('growth', domain: 'other.lendr.test');
    $link = supportLink($tenant, supportStaff());

    $this->get(str_replace('vozara.lendr.test', 'other.lendr.test', $link))->assertStatus(410);

    $this->assertGuest('web');
    expect(ImpersonationSession::first()->started_at)->toBeNull();
});

it('rejects the link if the user was suspended after it was issued', function () {
    $tenant = supportTenant();
    $user = supportStaff();
    $link = supportLink($tenant, $user);

    $user->update(['is_active' => false]);

    $this->get($link)->assertStatus(410);
    $this->assertGuest('web');
});

// ─── During a support session ────────────────────────────────────────────────

/** Enter a support session and return it, leaving the browser signed in. */
function enterSupportSession(Tenant $tenant, User $user): ImpersonationSession
{
    test()->get(supportLink($tenant, $user))->assertRedirect();

    return ImpersonationSession::latest('id')->first();
}

it('shows the banner data on tenant pages', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin, 'name' => 'Grace Admin']);
    enterSupportSession($tenant, $user);

    $this->get('/staff/profile')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('impersonation.target_name', 'Grace Admin'));
});

it('records the landlord against audit entries made during the session', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    $this->get('/staff/profile')->assertOk();
    activity()->log('during the session');

    $entry = Activity::where('description', 'during the session')->first();
    expect($entry->properties['impersonated_by'])->toBe($session->landlord_email);
});

it('does not tag audit entries outside a support session', function () {
    activity()->log('ordinary work');

    expect(Activity::where('description', 'ordinary work')->first()->properties)->not->toHaveKey('impersonated_by');
});

it('blocks changing the user\'s password or 2FA', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    enterSupportSession($tenant, $user);
    $password = $user->fresh()->password;

    $this->putJson(route('staff.profile.password'), [
        'current_password' => 'password',
        'password' => 'New-Secret#12345',
        'password_confirmation' => 'New-Secret#12345',
    ])->assertForbidden();

    $this->withHeaders(['Referer' => 'https://app.lendr.app/dashboard'])
        ->deleteJson(route('api.v1.auth.2fa.disable'))
        ->assertForbidden();

    expect($user->fresh()->password)->toBe($password);
});

it('signs out when the 30 minutes are up', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    $this->get('/staff/profile')->assertOk();

    $this->travel(ImpersonationSession::SESSION_MINUTES + 1)->minutes();
    app('auth')->forgetGuards();

    $this->get('/staff/profile')->assertRedirect();

    expect($session->fresh()->ended_reason)->toBe('expired');
    expect(session()->has(ImpersonationSession::SESSION_KEY))->toBeFalse();
});

it('can be ended from the landlord panel', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);
    $browserSession = session()->all();

    asLandlord()->postJson("/api/v1/landlord/impersonations/{$session->id}/end")
        ->assertOk()
        ->assertJsonPath('data.status', 'ended');

    asGuestBrowser()->withSession($browserSession)->get('/staff/profile')->assertRedirect();
    expect(session()->has(ImpersonationSession::SESSION_KEY))->toBeFalse();
});

it('ends when the session wanders onto another tenant\'s host', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    supportTenant('growth', domain: 'other.lendr.test');
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    app('auth')->forgetGuards();

    $this->get('http://other.lendr.test/staff/profile')->assertRedirect();

    expect($session->fresh()->ended_at)->not->toBeNull();
    $this->assertGuest('web');
});

it('leaves the landlord panel pages alone during a session', function () {
    $tenant = supportTenant('growth', domain: 'vozara.lendr.test');
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    $this->get('http://localhost/landlord/tenants')->assertOk();

    expect($session->fresh()->isLive())->toBeTrue();
});

it('ends the session and returns to the panel from the banner', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    config(['app.url' => 'https://lendr.test']);

    $this->post(route('impersonation.stop'))->assertRedirect('https://lendr.test/landlord/tenants');

    expect($session->fresh()->ended_reason)->toBe('stopped');
    expect(session()->has(ImpersonationSession::SESSION_KEY))->toBeFalse();
});

it('marks the session ended when they use the normal log out', function () {
    $tenant = supportTenant();
    $user = supportStaff(['role' => UserRole::SuperAdmin]);
    $session = enterSupportSession($tenant, $user);

    $this->post(route('portal.logout'))->assertRedirect();

    expect($session->fresh()->ended_reason)->toBe('logout');
});

it('lists a tenant\'s support history, newest first, without other tenants', function () {
    $tenant = supportTenant();
    $other = supportTenant();
    $user = supportStaff();
    supportLink($tenant, $user);
    supportLink($other, $user);

    $response = asLandlord()->getJson("/api/v1/landlord/tenants/{$tenant->id}/impersonations")->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('pending')
        ->and($response->getContent())->not->toContain('token_hash');
});
