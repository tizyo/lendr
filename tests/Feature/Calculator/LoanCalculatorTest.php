<?php

use App\Enums\UserRole;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use App\Services\LoanCalculatorService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function calcAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function calcPlan(array $extra = []): LoanPlan
{
    $lt = LoanType::first() ?? LoanType::create(['name' => 'Calc Type', 'code' => 'CT', 'is_active' => true]);

    return LoanPlan::create(array_merge([
        'loan_type_id'       => $lt->id,
        'name'               => 'Standard Plan',
        'code'               => 'SP-' . uniqid(),
        'interest_rate'      => 15,
        'interest_type'      => 'flat',
        'interest_period'    => 'monthly',
        'min_tenure'         => 1,
        'max_tenure'         => 24,
        'tenure_type'        => 'months',
        'min_amount'         => 500,
        'max_amount'         => 100000,
        'penalty_rate'       => 2,
        'penalty_type'       => 'flat',
        'grace_period_days'  => 0,
        'repayment_schedule' => 'monthly',
        'processing_fee'     => 2,
        'insurance_fee'      => 1,
        'is_active'          => true,
    ], $extra));
}

// ─── Service unit tests ───────────────────────────────────────────────────────

test('calculator generates correct number of instalments', function () {
    $plan   = calcPlan();
    $svc    = app(LoanCalculatorService::class);
    $result = $svc->calculate($plan, 12000, 12, now()->toDateString());

    expect($result['schedule'])->toHaveCount(12);
});

test('flat rate calculator totals match', function () {
    $plan   = calcPlan(['interest_type' => 'flat', 'interest_rate' => 10, 'interest_period' => 'monthly']);
    $svc    = app(LoanCalculatorService::class);
    $result = $svc->calculateAmounts($plan, 10000, 12);

    // flat: 10% per month × 12 months = 120% of principal
    expect($result['interest_amount'])->toBe(12000.0)
        ->and($result['principal_amount'])->toBe(10000.0);
});

test('preview method calculates without a plan record', function () {
    $svc    = app(LoanCalculatorService::class);
    $result = $svc->preview([
        'principal'          => 5000,
        'interest_rate'      => 20,
        'interest_type'      => 'flat',
        'interest_period'    => 'monthly',
        'tenure'             => 6,
        'tenure_type'        => 'months',
        'repayment_schedule' => 'monthly',
        'processing_fee'     => 3,
        'insurance_fee'      => 0,
    ]);

    expect($result['schedule'])->toHaveCount(6)
        ->and($result['interest_amount'])->toBeGreaterThan(0);
});

test('bullet schedule produces single instalment', function () {
    $plan   = calcPlan(['repayment_schedule' => 'bullet']);
    $svc    = app(LoanCalculatorService::class);
    $result = $svc->calculate($plan, 5000, 3, now()->toDateString());

    expect($result['schedule'])->toHaveCount(1);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('POST calculator/calculate with plan_id returns schedule', function () {
    $admin = calcAdmin();
    $plan  = calcPlan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.calculator.calculate'), [
            'plan_id'   => $plan->id,
            'principal' => 10000,
            'tenure'    => 12,
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['schedule', 'interest_amount', 'total_payable']]);
});

test('POST calculator/calculate with raw params returns schedule', function () {
    $admin = calcAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.calculator.calculate'), [
            'principal'          => 8000,
            'interest_rate'      => 18,
            'interest_type'      => 'flat',
            'interest_period'    => 'monthly',
            'tenure'             => 6,
            'tenure_type'        => 'months',
            'repayment_schedule' => 'monthly',
        ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['schedule', 'interest_amount', 'total_payable']]);
});

test('POST calculator/calculate validates required fields', function () {
    $admin = calcAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.calculator.calculate'), [
            'principal' => 5000,
            // missing required fields for raw mode
        ])
        ->assertStatus(422);
});

test('POST calculator/calculate returns correct instalment count', function () {
    $admin = calcAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.calculator.calculate'), [
            'principal'          => 12000,
            'interest_rate'      => 15,
            'interest_type'      => 'flat',
            'interest_period'    => 'monthly',
            'tenure'             => 12,
            'tenure_type'        => 'months',
            'repayment_schedule' => 'monthly',
        ])
        ->assertOk();

    expect($resp->json('data.schedule'))->toHaveCount(12);
});

test('unauthenticated cannot use calculator', function () {
    $this->postJson(route('api.v1.calculator.calculate'), [
        'principal' => 5000,
    ])->assertStatus(401);
});
