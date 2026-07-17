<?php

use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Services\LoanCalculatorService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function makePlan(array $overrides = []): LoanPlan
{
    $lt = LoanType::factory()->create();

    return LoanPlan::factory()->create(array_merge([
        'loan_type_id'         => $lt->id,
        'interest_rate'        => 10,          // 10%
        'interest_type'        => 'flat',
        'repayment_frequency'  => 'monthly',
        'processing_fee'       => 2,           // 2%
        'insurance_fee'        => 0,
        'grace_period_days'    => 0,
    ], $overrides));
}

// ─── Flat interest ────────────────────────────────────────────────────────────

test('flat interest calculation is correct', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'interest_rate' => 10]);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 10000, 12);

    expect($result['principal_amount'])->toBe(10000.0)
        ->and($result['interest_amount'])->toBe(1000.0)   // 10% of 10000 flat
        ->and($result['processing_fee'])->toBe(200.0);    // 2%
});

test('flat interest total payable equals principal plus interest plus fees', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'interest_rate' => 15, 'insurance_fee' => 1]);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 5000, 6);

    $expected = 5000 + (5000 * 0.15) + (5000 * 0.02) + (5000 * 0.01);
    expect($result['total_payable'])->toBe(round($expected, 2));
});

// ─── Reducing balance interest ────────────────────────────────────────────────

test('reducing balance produces lower total interest than flat', function () {
    $flatPlan     = makePlan(['interest_type' => 'flat',              'interest_rate' => 10]);
    $reducingPlan = makePlan(['interest_type' => 'reducing_balance',  'interest_rate' => 10]);
    $svc          = new LoanCalculatorService;

    $flat     = $svc->calculateAmounts($flatPlan,     10000, 12);
    $reducing = $svc->calculateAmounts($reducingPlan, 10000, 12);

    expect($reducing['interest_amount'])->toBeLessThan($flat['interest_amount']);
});

test('reducing balance interest amount is positive', function () {
    $plan   = makePlan(['interest_type' => 'reducing_balance', 'interest_rate' => 18]);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 8000, 6);

    expect($result['interest_amount'])->toBeGreaterThan(0);
});

// ─── Schedule generation ──────────────────────────────────────────────────────

test('schedule row count matches tenure in months', function () {
    $plan     = makePlan(['interest_type' => 'flat', 'repayment_frequency' => 'monthly']);
    $svc      = new LoanCalculatorService;
    $result   = $svc->calculate($plan, 6000, 6, now()->toDateString());

    expect(count($result['schedule']))->toBe(6);
});

test('schedule instalment amounts sum to total payable', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'repayment_frequency' => 'monthly']);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculate($plan, 12000, 12, now()->toDateString());

    $scheduleSum = array_sum(array_column($result['schedule'], 'total_due'));

    // Allow ±0.02 for rounding across instalments
    expect(abs($scheduleSum - $result['total_payable']))->toBeLessThan(0.03);
});

test('schedule due dates are sequential and monthly', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'repayment_frequency' => 'monthly']);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculate($plan, 6000, 3, '2026-01-01');

    $dates = array_column($result['schedule'], 'due_date');

    expect($dates[0])->toBe('2026-02-01')
        ->and($dates[1])->toBe('2026-03-01')
        ->and($dates[2])->toBe('2026-04-01');
});

test('zero interest rate produces no interest amount', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'interest_rate' => 0, 'processing_fee' => 0]);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 5000, 6);

    expect($result['interest_amount'])->toBe(0.0)
        ->and($result['total_payable'])->toBe(5000.0);
});

// ─── BCMath precision ─────────────────────────────────────────────────────────

test('amounts use bcmath-safe decimal precision', function () {
    $plan   = makePlan(['interest_type' => 'flat', 'interest_rate' => 7.5, 'processing_fee' => 1.5]);
    $svc    = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 7777, 9);

    // Result must be a float with exactly 2dp — not a float rounding error
    expect(fmod($result['interest_amount'] * 100, 1))->toBe(0.0);
});
