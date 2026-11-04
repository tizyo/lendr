<?php

use App\Enums\UserRole;
use App\Models\Landlord\GhostUser;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\User;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/*
 * auth:sanctum accepts any valid token whatever model owns it, so before the
 * actor:* guard a borrower's app token or a public repo-marketplace token passed
 * every staff API route; and ~360 staff routes checked no permission at all, so
 * any staff member (a Cashier included) could create API keys, pay commissions,
 * bulk-disburse loans and read the GL.
 */

/** Routes any signed-in staff member may use: their own account and notifications. */
const PERSONAL_STAFF_ROUTES = [
    'api.v1.auth.me', 'api.v1.auth.logout', 'api.v1.auth.refresh', 'api.v1.auth.2fa.setup', 'api.v1.auth.2fa.verify',
    'api.v1.auth.2fa.challenge', 'api.v1.auth.2fa.disable',
    'api.v1.dashboard.kpis', 'api.v1.dashboard.charts',
    'api.v1.notifications.index', 'api.v1.notifications.read', 'api.v1.notifications.read-all',
    'api.v1.notification-preferences.index', 'api.v1.notification-preferences.update',
    'api.v1.onboarding.progress',
    'dashboard', 'portal.dashboard', 'banners.dismiss', 'logout', 'portal.logout',
    'staff.profile', 'staff.profile.update', 'staff.profile.password',
    'support.index', 'support.store', 'support.show', 'support.reply',
    'impersonation.stop',
];

function middlewareNames(RouteDefinition $route): array
{
    return $route->gatherMiddleware();
}

it('puts an audience guard on every token-authenticated API route', function () {
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $mw = middlewareNames($route);
        if (! in_array('auth:sanctum', $mw, true)) {
            continue;
        }
        $guarded = collect($mw)->contains(fn ($m) => str_starts_with($m, 'actor:') || str_starts_with($m, 'landlord.token'));
        if (! $guarded) {
            $missing[] = $route->methods()[0].' '.$route->uri();
        }
    }

    expect($missing)->toBe([]);
});

it('requires a permission on every staff route except the personal ones', function () {
    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $mw = middlewareNames($route);
        $staff = in_array('actor:staff', $mw, true) || in_array('auth', $mw, true);
        if (! $staff || in_array($route->getName(), PERSONAL_STAFF_ROUTES, true)) {
            continue;
        }
        if (str_starts_with($route->uri(), 'landlord') || str_starts_with($route->uri(), 'api/v1/landlord')) {
            continue;
        }
        if (! collect($mw)->contains(fn ($m) => str_starts_with($m, 'permission:'))) {
            $missing[] = $route->methods()[0].' '.$route->uri().' ('.$route->getName().')';
        }
    }

    expect($missing)->toBe([]);
});

it('refuses a borrower app token on staff endpoints', function () {
    $token = Borrower::factory()->create()->createToken('pwa-session')->plainTextToken;

    foreach (['/api/v1/agents', '/api/v1/api-clients', '/api/v1/savings', '/api/v1/investors', '/api/v1/gl/accounts', '/api/v1/risk-policies', '/api/v1/auth/me'] as $url) {
        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->getJson($url)
            ->assertForbidden();
    }
});

it('refuses a repo-marketplace token on staff and borrower endpoints', function () {
    $ghost = GhostUser::create(['name' => 'Anyone', 'phone' => '0970000001', 'is_phone_verified' => true]);
    $token = $ghost->createToken('marketplace')->plainTextToken;

    foreach (['/api/v1/agents', '/api/v1/savings', '/api/v1/investors', '/api/v1/me'] as $url) {
        $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
            ->getJson($url)
            ->assertForbidden();
    }
});

it('keeps workspace branding public for login screens', function () {
    $this->getJson('/api/v1/branding')->assertOk();
    $this->getJson('/api/v1/settings/branding')->assertOk();
});

it('refuses a staff token on borrower endpoints', function () {
    $token = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true])->createToken('api')->plainTextToken;

    $this->withHeaders(['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'])
        ->getJson('/api/v1/me')
        ->assertForbidden();
});

it('still lets a borrower use their own app and sign out', function () {
    $borrower = Borrower::factory()->create();
    $token = $borrower->createToken('pwa-session')->plainTextToken;
    $headers = ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];

    $this->withHeaders($headers)->getJson('/api/v1/me')->assertOk();
    $this->withHeaders($headers)->postJson('/api/v1/borrower/auth/logout')->assertOk();

    expect($borrower->tokens()->count())->toBe(0);
});

it('keeps sensitive modules away from roles without the permission', function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    $cashier = User::factory()->create(['role' => UserRole::Cashier, 'is_active' => true]);

    $this->actingAs($cashier)->postJson('/api/v1/api-clients', ['name' => 'x'])->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/investors')->assertForbidden();
    $this->actingAs($cashier)->getJson('/api/v1/gl/accounts')->assertForbidden();
    $this->actingAs($cashier)->postJson(route('bulk.loans.disburse'), ['loan_ids' => [1]])->assertForbidden();
    $this->actingAs($cashier)->postJson(route('bulk.loans.approve'), ['loan_ids' => [1]])->assertForbidden();
    $this->actingAs($cashier)->get(route('billing.index'))->assertForbidden();

    // What a cashier does need still works.
    $this->actingAs($cashier)->getJson('/api/v1/savings')->assertOk();
});

it('gives each role the modules its job needs', function () {
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();

    $expect = [
        UserRole::BranchManager->value => ['savings.manage', 'collections.manage', 'approvals.act', 'sales.targets', 'marketplace.manage'],
        UserRole::LoanOfficer->value => ['savings.manage', 'collections.manage', 'sales.manage', 'crb.check'],
        UserRole::Cashier->value => ['savings.transact', 'collections.view'],
        UserRole::Accountant->value => ['accounting.manage', 'investors.manage', 'commissions.approve', 'compliance.manage'],
        UserRole::Auditor->value => ['accounting.view', 'audit_log.view', 'investors.view'],
    ];
    $never = [
        UserRole::LoanOfficer->value => ['accounting.manage', 'integrations.manage', 'billing.manage', 'approvals.act'],
        UserRole::Cashier->value => ['loans.approve', 'integrations.manage', 'accounting.view'],
        UserRole::Auditor->value => ['accounting.manage', 'savings.transact', 'loans.approve'],
    ];

    foreach ($expect as $role => $perms) {
        $user = User::factory()->create(['role' => UserRole::from($role), 'is_active' => true]);
        foreach ($perms as $perm) {
            expect($user->can($perm))->toBeTrue("{$role} should have {$perm}");
        }
        foreach ($never[$role] ?? [] as $perm) {
            expect($user->can($perm))->toBeFalse("{$role} must not have {$perm}");
        }
    }
});
