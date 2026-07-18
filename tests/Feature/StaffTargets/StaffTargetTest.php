<?php

use App\Enums\UserRole;
use App\Models\Tenant\StaffTarget;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function targetAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function targetOfficer(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('can create a staff target', function () {
    $admin = targetAdmin();
    $officer = targetOfficer();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.staff-targets.upsert'), [
            'user_id' => $officer->id,
            'period_month' => 3,
            'period_year' => 2026,
            'disbursement_target' => 100000,
            'collection_target' => 80000,
            'new_borrowers_target' => 10,
            'new_loans_target' => 15,
        ])
        ->assertStatus(201);

    expect($resp->json('data.target.disbursement_target'))->toEqual(100000);

    $this->assertDatabaseHas('staff_targets', [
        'user_id' => $officer->id,
        'period_month' => 3,
        'period_year' => 2026,
    ]);
})->group('staff-targets');

test('upserting same period updates existing target', function () {
    $admin = targetAdmin();
    $officer = targetOfficer();

    $payload = ['user_id' => $officer->id, 'period_month' => 3, 'period_year' => 2026, 'disbursement_target' => 100000];

    $this->actingAs($admin)->postJson(route('api.v1.staff-targets.upsert'), $payload);
    $this->actingAs($admin)->postJson(route('api.v1.staff-targets.upsert'), array_merge($payload, ['disbursement_target' => 200000]));

    expect(StaffTarget::count())->toBe(1);
    expect((float) StaffTarget::first()->disbursement_target)->toBe(200000.0);
})->group('staff-targets');

test('can list targets for a period', function () {
    $admin = targetAdmin();
    $o1 = targetOfficer();
    $o2 = targetOfficer();

    StaffTarget::create(['user_id' => $o1->id, 'period_month' => 3, 'period_year' => 2026, 'disbursement_target' => 0, 'collection_target' => 0, 'new_borrowers_target' => 0, 'new_loans_target' => 0]);
    StaffTarget::create(['user_id' => $o2->id, 'period_month' => 3, 'period_year' => 2026, 'disbursement_target' => 0, 'collection_target' => 0, 'new_borrowers_target' => 0, 'new_loans_target' => 0]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.staff-targets.index', ['year' => 2026, 'month' => 3]))
        ->assertOk();

    expect(count($resp->json('data.targets')))->toBe(2);
})->group('staff-targets');

test('target response includes actuals and achievement', function () {
    $admin = targetAdmin();
    $officer = targetOfficer();

    $this->actingAs($admin)
        ->postJson(route('api.v1.staff-targets.upsert'), [
            'user_id' => $officer->id,
            'period_month' => 3,
            'period_year' => 2026,
            'disbursement_target' => 50000,
            'collection_target' => 40000,
            'new_borrowers_target' => 5,
            'new_loans_target' => 8,
        ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.staff-targets.index', ['year' => 2026, 'month' => 3]))
        ->assertOk();

    $target = $resp->json('data.targets.0');
    expect($target)->toHaveKeys(['actuals', 'achievement']);
    expect($target['actuals'])->toHaveKeys(['disbursement_actual', 'collection_actual', 'new_borrowers_actual']);
})->group('staff-targets');

test('can delete a staff target', function () {
    $admin = targetAdmin();
    $officer = targetOfficer();

    $target = StaffTarget::create([
        'user_id' => $officer->id, 'period_month' => 3, 'period_year' => 2026,
        'disbursement_target' => 0, 'collection_target' => 0, 'new_borrowers_target' => 0, 'new_loans_target' => 0,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('api.v1.staff-targets.destroy', $target))
        ->assertOk();

    $this->assertDatabaseMissing('staff_targets', ['id' => $target->id]);
})->group('staff-targets');

test('performance endpoint returns team summary', function () {
    $admin = targetAdmin();
    $officer = targetOfficer();

    StaffTarget::create([
        'user_id' => $officer->id, 'period_month' => 3, 'period_year' => 2026,
        'disbursement_target' => 100000, 'collection_target' => 80000,
        'new_borrowers_target' => 10, 'new_loans_target' => 15,
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.staff-targets.performance', ['year' => 2026, 'month' => 3]))
        ->assertOk();

    expect($resp->json('data'))->toHaveKeys(['period', 'team', 'totals']);
    expect($resp->json('data.totals.disbursement_target'))->toEqual(100000);
})->group('staff-targets');

test('upsert requires valid user_id and period', function () {
    $admin = targetAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.staff-targets.upsert'), [])
        ->assertJsonValidationErrors(['user_id', 'period_month', 'period_year']);
})->group('staff-targets');

test('unauthenticated cannot access staff targets', function () {
    $this->getJson(route('api.v1.staff-targets.index'))->assertStatus(401);
})->group('staff-targets');
