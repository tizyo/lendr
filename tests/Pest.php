<?php

use Tests\Traits\RunsTenantMigrations;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
*/
uses(
    Tests\TestCase::class,
    RunsTenantMigrations::class,
)->in('Feature');

uses(Tests\TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
*/
expect()->extend('toBeZmwAmount', function () {
    return $this->toMatch('/^\d+\.\d{2}$/');
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
function actingAsAdmin(): \App\Models\Tenant\User
{
    (new \Database\Seeders\RolesAndPermissionsSeeder)->run();

    // role=SuperAdmin triggers User::booted()'s save hook, which syncs the
    // matching (now-seeded, fully-permissioned) spatie role automatically.
    return \App\Models\Tenant\User::factory()->create([
        'role' => \App\Enums\UserRole::SuperAdmin,
        'is_active' => true,
    ]);
}

function makePlan(array $overrides = []): \App\Models\Tenant\LoanPlan
{
    $type = \App\Models\Tenant\LoanType::factory()->create();

    return \App\Models\Tenant\LoanPlan::factory()->create(array_merge([
        'loan_type_id' => $type->id,
        'interest_rate' => 10,          // 10%
        'interest_type' => 'flat',
        'repayment_schedule' => 'monthly',
        'processing_fee' => 2,           // 2%
        'insurance_fee' => 0,
        'grace_period_days' => 0,
    ], $overrides));
}
