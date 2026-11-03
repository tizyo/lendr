<?php

use App\Models\Tenant\LoanType;

/*
 * Loan types and plans could not be created or edited by anyone: the requests
 * checked a "settings.manage" permission the roles seeder never creates. They now
 * check the same loan_products.* permissions as their routes. Uses the seeded
 * roles (actingAsAdmin), as production does, rather than ad-hoc permissions.
 */

it('lets an admin create a loan type', function () {
    $this->actingAs(actingAsAdmin())->postJson(route('api.v1.loan-types.store'), [
        'name' => 'Personal Loan',
        'code' => 'PERSONAL',
        'is_active' => true,
        'sort_order' => 0,
    ])->assertCreated()->assertJsonPath('data.code', 'PERSONAL');

    expect(LoanType::where('code', 'PERSONAL')->exists())->toBeTrue();
});

it('lets an admin edit a loan type', function () {
    $type = LoanType::create(['name' => 'Business', 'code' => 'BUS', 'is_active' => true]);

    $this->actingAs(actingAsAdmin())->putJson(route('api.v1.loan-types.update', $type), ['name' => 'Business Loan'])
        ->assertOk()->assertJsonPath('data.name', 'Business Loan');
});

it('lets an admin create and edit a loan plan', function () {
    $admin = actingAsAdmin();
    $type = LoanType::create(['name' => 'Business', 'code' => 'BUS2', 'is_active' => true]);

    $planId = $this->actingAs($admin)->postJson(route('api.v1.loan-plans.store'), [
        'loan_type_id' => $type->id,
        'name' => 'Six Month Business',
        'code' => 'BUS-6M',
        'interest_rate' => 10,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'min_tenure' => 1,
        'max_tenure' => 6,
        'tenure_type' => 'months',
        'min_amount' => 500,
        'max_amount' => 50000,
        'repayment_schedule' => 'monthly',
    ])->assertCreated()->json('data.id');

    $this->actingAs($admin)->putJson(route('api.v1.loan-plans.update', $planId), ['interest_rate' => 12])
        ->assertOk();
});

it('still refuses staff without loan product permissions', function () {
    actingAsAdmin(); // seeds roles
    $auditor = \App\Models\Tenant\User::factory()->create(['role' => \App\Enums\UserRole::Auditor, 'is_active' => true]);

    $this->actingAs($auditor)->postJson(route('api.v1.loan-types.store'), ['name' => 'X', 'code' => 'X'])
        ->assertForbidden();
});
