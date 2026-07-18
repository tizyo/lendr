<?php

use App\Enums\UserRole;
use App\Models\Landlord\PlanConfig;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Branch;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function planAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function setPlanLimit(string $feature, int $limit): void
{
    // Ensure a plan config exists for 'starter' with the given limit
    $existing = PlanConfig::where('plan', 'starter')->first();
    $features = $existing ? (array) $existing->features : [];
    $features[$feature] = $limit;

    PlanConfig::updateOrCreate(
        ['plan' => 'starter'],
        ['label' => 'Starter', 'features' => $features],
    );

    // Point the tenant at 'starter' plan via the settings or tenant table
    // The service reads tenancy()->tenant?->plan, so we mock via DB
    DB::table('tenants')->update(['plan' => 'starter']);
}

// ─── Staff user limit ─────────────────────────────────────────────────────────

test('staff creation is blocked when plan user limit is reached', function () {
    $admin = planAdmin();
    setPlanLimit('max_users', 1); // 1 user already exists (admin)

    $this->actingAs($admin)
        ->postJson(route('api.v1.staff.store'), [
            'name' => 'New Staff',
            'email' => 'newstaff@test.com',
            'role' => UserRole::LoanOfficer->value,
            'department' => 'Operations',
            'phone' => '0971000001',
        ])
        ->assertStatus(403)
        ->assertJsonPath('message', fn ($msg) => str_contains(strtolower($msg), 'limit'));
});

test('staff creation succeeds when below plan user limit', function () {
    $admin = planAdmin();
    setPlanLimit('max_users', 10);

    $this->actingAs($admin)
        ->postJson(route('api.v1.staff.store'), [
            'name' => 'New Staff',
            'email' => 'newstaff@test.com',
            'role' => UserRole::LoanOfficer->value,
            'department' => 'Operations',
            'phone' => '0971000001',
        ])
        ->assertStatus(201);
});

test('plan limit of -1 means unlimited staff users', function () {
    $admin = planAdmin();
    setPlanLimit('max_users', -1); // unlimited

    // Create 5 users to simulate high count
    User::factory()->count(5)->create(['is_active' => true]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.staff.store'), [
            'name' => 'New Staff Unlimited',
            'email' => 'newstaff_ul@test.com',
            'role' => UserRole::LoanOfficer->value,
            'department' => 'Operations',
            'phone' => '0971000002',
        ])
        ->assertStatus(201);
});

// ─── Branch limit ─────────────────────────────────────────────────────────────

test('branch creation is blocked when plan branch limit is reached', function () {
    $admin = planAdmin();
    setPlanLimit('max_branches', 1);

    Branch::create(['name' => 'Existing Branch', 'code' => 'HQ01', 'is_active' => true]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.branches.store'), [
            'name' => 'Second Branch',
            'code' => 'BR02',
        ])
        ->assertStatus(403);
});

test('branch creation succeeds when below plan branch limit', function () {
    $admin = planAdmin();
    setPlanLimit('max_branches', 5);

    $this->actingAs($admin)
        ->postJson(route('api.v1.branches.store'), [
            'name' => 'New Branch',
            'code' => 'NB01',
        ])
        ->assertStatus(201);
});

// ─── Borrower limit ───────────────────────────────────────────────────────────

test('borrower creation is blocked when plan borrower limit is reached', function () {
    $admin = planAdmin();
    setPlanLimit('max_borrowers', 1);

    Borrower::factory()->create();

    $this->actingAs($admin)
        ->postJson(route('api.v1.borrowers.store'), [
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'phone' => '0971111111',
            'national_id' => 'NRC123456',
        ])
        ->assertStatus(403);
});

test('borrower creation succeeds when below plan borrower limit', function () {
    $admin = planAdmin();
    setPlanLimit('max_borrowers', 100);

    $this->actingAs($admin)
        ->postJson(route('api.v1.borrowers.store'), [
            'first_name' => 'Test',
            'last_name' => 'Borrower',
            'phone' => '0971111111',
            'national_id' => 'NRC123456',
        ])
        ->assertStatus(201);
});
