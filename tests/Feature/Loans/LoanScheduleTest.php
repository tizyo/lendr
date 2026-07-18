<?php

use App\Services\LoanCalculatorService;

// ─── Flat interest ────────────────────────────────────────────────────────────

test('flat interest calculation is correct', function () {
    // interest_period defaults to 'monthly' — flat rate is per-period,
    // scaled by the number of periods in the tenure (10%/month * 12 = 120%).
    $plan = makePlan(['interest_type' => 'flat', 'interest_rate' => 10]);
    $svc = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 10000, 12);

    expect($result['principal_amount'])->toBe(10000.0)
        ->and($result['interest_amount'])->toBe(12000.0)  // 10%/month * 12 months
        ->and($result['processing_fee'])->toBe(200.0);    // 2%
});

test('flat interest total payable equals principal plus interest plus fees', function () {
    $plan = makePlan(['interest_type' => 'flat', 'interest_rate' => 15, 'insurance_fee' => 1]);
    $svc = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 5000, 6);

    $expected = 5000 + (5000 * 0.15 * 6) + (5000 * 0.02) + (5000 * 0.01);
    expect($result['total_payable'])->toBe(round($expected, 2));
});

// ─── Reducing balance interest ────────────────────────────────────────────────

test('reducing balance produces lower total interest than flat', function () {
    $flatPlan = makePlan(['interest_type' => 'flat',              'interest_rate' => 10]);
    $reducingPlan = makePlan(['interest_type' => 'reducing_balance',  'interest_rate' => 10]);
    $svc = new LoanCalculatorService;

    $flat = $svc->calculateAmounts($flatPlan, 10000, 12);
    $reducing = $svc->calculateAmounts($reducingPlan, 10000, 12);

    expect($reducing['interest_amount'])->toBeLessThan($flat['interest_amount']);
});

test('reducing balance interest amount is positive', function () {
    $plan = makePlan(['interest_type' => 'reducing_balance', 'interest_rate' => 18]);
    $svc = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 8000, 6);

    expect($result['interest_amount'])->toBeGreaterThan(0);
});

// ─── Schedule generation ──────────────────────────────────────────────────────

test('schedule row count matches tenure in months', function () {
    $plan = makePlan(['interest_type' => 'flat', 'repayment_schedule' => 'monthly']);
    $svc = new LoanCalculatorService;
    $result = $svc->calculate($plan, 6000, 6, now()->toDateString());

    expect(count($result['schedule']))->toBe(6);
});

test('schedule instalment amounts sum to principal plus interest', function () {
    // Processing/insurance fees are collected upfront at disbursement, not
    // amortized into the repayment schedule — schedule rows carry principal
    // + interest only, so they sum to less than total_payable by the fee amount.
    $plan = makePlan(['interest_type' => 'flat', 'repayment_schedule' => 'monthly']);
    $svc = new LoanCalculatorService;
    $result = $svc->calculate($plan, 12000, 12, now()->toDateString());

    $scheduleSum = array_sum(array_column($result['schedule'], 'total_due'));
    $expected = $result['principal_amount'] + $result['interest_amount'];

    // Allow ±0.02 for rounding across instalments
    expect(abs($scheduleSum - $expected))->toBeLessThan(0.03);
});

test('schedule due dates are sequential and monthly', function () {
    $plan = makePlan(['interest_type' => 'flat', 'repayment_schedule' => 'monthly']);
    $svc = new LoanCalculatorService;
    $result = $svc->calculate($plan, 6000, 3, '2026-01-01');

    $dates = array_column($result['schedule'], 'due_date');

    expect($dates[0])->toBe('2026-02-01')
        ->and($dates[1])->toBe('2026-03-01')
        ->and($dates[2])->toBe('2026-04-01');
});

test('zero interest rate produces no interest amount', function () {
    $plan = makePlan(['interest_type' => 'flat', 'interest_rate' => 0, 'processing_fee' => 0]);
    $svc = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 5000, 6);

    expect($result['interest_amount'])->toBe(0.0)
        ->and($result['total_payable'])->toBe(5000.0);
});

// ─── BCMath precision ─────────────────────────────────────────────────────────

test('amounts use bcmath-safe decimal precision', function () {
    $plan = makePlan(['interest_type' => 'flat', 'interest_rate' => 7.5, 'processing_fee' => 1.5]);
    $svc = new LoanCalculatorService;
    $result = $svc->calculateAmounts($plan, 7777, 9);

    // Result must be a float with exactly 2dp — not a float rounding error
    expect(fmod($result['interest_amount'] * 100, 1))->toBe(0.0);
});
