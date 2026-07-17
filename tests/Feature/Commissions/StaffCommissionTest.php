<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\CommissionRule;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StaffCommission;
use App\Models\Tenant\User;
use App\Services\CommissionService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function commAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function commOfficer(): User
{
    return User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
}

function commLoan(User $officer, array $extra = []): Loan
{
    $borrower = Borrower::factory()->create(['is_active' => true]);
    $lt       = LoanType::first() ?? LoanType::create(['name' => 'Comm Type', 'code' => 'CM', 'is_active' => true]);
    $plan     = LoanPlan::where('loan_type_id', $lt->id)->first() ?? LoanPlan::create([
        'loan_type_id'       => $lt->id,
        'name'               => 'Comm Plan',
        'code'               => 'CP-' . uniqid(),
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
        'insurance_fee'      => 0,
        'is_active'          => true,
    ]);

    return Loan::create(array_merge([
        'loan_number'        => 'LN-CM-' . fake()->unique()->numerify('####'),
        'borrower_id'        => $borrower->id,
        'loan_type_id'       => $lt->id,
        'loan_plan_id'       => $plan->id,
        'created_by'         => $officer->id,
        'principal_amount'   => 10000,
        'interest_amount'    => 1500,
        'processing_fee'     => 200,
        'insurance_fee'      => 0,
        'total_payable'      => 11500,
        'total_paid'         => 0,
        'outstanding_balance'=> 11500,
        'penalty_balance'    => 0,
        'interest_rate'      => 15,
        'interest_type'      => 'flat',
        'interest_period'    => 'monthly',
        'tenure'             => 12,
        'tenure_type'        => 'months',
        'repayment_schedule' => 'monthly',
        'status'             => 'disbursed',
        'application_date'   => now()->toDateString(),
        'disbursement_date'  => now()->toDateString(),
    ], $extra));
}

// ─── CommissionRule model tests ───────────────────────────────────────────────

test('percentage commission rule calculates correctly', function () {
    $rule = CommissionRule::create([
        'trigger'   => 'disbursement',
        'calc_type' => 'percentage',
        'rate'      => 1.5,
        'is_active' => true,
    ]);

    expect($rule->calculate(10000))->toBe(150.0);
});

test('flat commission rule calculates correctly', function () {
    $rule = CommissionRule::create([
        'trigger'   => 'disbursement',
        'calc_type' => 'flat',
        'rate'      => 250,
        'is_active' => true,
    ]);

    expect($rule->calculate(10000))->toBe(250.0);
});

test('commission rule returns 0 when below min_amount', function () {
    $rule = CommissionRule::create([
        'trigger'    => 'disbursement',
        'calc_type'  => 'percentage',
        'rate'       => 2,
        'min_amount' => 5000,
        'is_active'  => true,
    ]);

    expect($rule->calculate(4000))->toBe(0.0);
});

// ─── CommissionService unit tests ─────────────────────────────────────────────

test('calculateForDisbursement creates commission when matching rule exists', function () {
    $officer = commOfficer();
    $loan    = commLoan($officer);

    CommissionRule::create([
        'trigger'   => 'disbursement',
        'calc_type' => 'percentage',
        'rate'      => 1.0,
        'is_active' => true,
    ]);

    $svc       = app(CommissionService::class);
    $created   = $svc->calculateForDisbursement($loan);

    expect($created)->toHaveCount(1)
        ->and((float) $created[0]->commission_amount)->toBe(100.0)  // 1% of 10000
        ->and((string) $created[0]->status)->toBe('pending');
});

test('calculateForDisbursement returns empty array when no matching rules', function () {
    $officer = commOfficer();
    $loan    = commLoan($officer);

    $svc   = app(CommissionService::class);
    $created = $svc->calculateForDisbursement($loan);
    expect($created)->toHaveCount(0);
});

test('user-specific rule only applies to that user', function () {
    $officer1 = commOfficer();
    $officer2 = commOfficer();
    $loan1    = commLoan($officer1);
    $loan2    = commLoan($officer2);

    CommissionRule::create([
        'user_id'   => $officer1->id,
        'trigger'   => 'disbursement',
        'calc_type' => 'percentage',
        'rate'      => 2.0,
        'is_active' => true,
    ]);

    $svc = app(CommissionService::class);
    expect($svc->calculateForDisbursement($loan1))->toHaveCount(1);
    expect($svc->calculateForDisbursement($loan2))->toHaveCount(0);
});

test('approvePeriod approves all pending commissions for month', function () {
    $admin   = commAdmin();
    $officer = commOfficer();

    StaffCommission::create([
        'user_id'           => $officer->id,
        'trigger'           => 'disbursement',
        'base_amount'       => 10000,
        'commission_amount' => 100,
        'status'            => 'pending',
        'period_month'      => '2026-03-01',
    ]);

    $svc   = app(CommissionService::class);
    $count = $svc->approvePeriod('2026-03', $admin->id);

    expect($count)->toBe(1);
    expect(StaffCommission::where('user_id', $officer->id)->first()->status)->toBe('approved');
});

test('markPaid marks approved commissions as paid', function () {
    $admin   = commAdmin();
    $officer = commOfficer();

    $comm = StaffCommission::create([
        'user_id'           => $officer->id,
        'trigger'           => 'disbursement',
        'base_amount'       => 10000,
        'commission_amount' => 100,
        'status'            => 'approved',
        'period_month'      => now()->startOfMonth()->toDateString(),
    ]);

    $svc   = app(CommissionService::class);
    $count = $svc->markPaid([$comm->id], $admin->id);

    expect($count)->toBe(1);
    expect(StaffCommission::find($comm->id)->status)->toBe('paid');
});

test('summary returns correct totals', function () {
    $officer = commOfficer();

    StaffCommission::create(['user_id' => $officer->id, 'trigger' => 'disbursement', 'base_amount' => 10000, 'commission_amount' => 100, 'status' => 'pending', 'period_month' => '2026-03-01']);
    StaffCommission::create(['user_id' => $officer->id, 'trigger' => 'disbursement', 'base_amount' => 5000,  'commission_amount' => 50,  'status' => 'paid',    'period_month' => '2026-03-01']);

    $svc     = app(CommissionService::class);
    $summary = $svc->summary($officer->id, '2026-03');

    expect($summary['pending']['amount'])->toBe(100.0)
        ->and($summary['paid']['amount'])->toBe(50.0)
        ->and($summary['total']['count'])->toBe(2);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('GET commission-rules returns list', function () {
    $admin = commAdmin();

    CommissionRule::create(['trigger' => 'disbursement', 'calc_type' => 'percentage', 'rate' => 1, 'is_active' => true]);

    $this->actingAs($admin)
        ->getJson(route('api.v1.commission-rules.index'))
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'trigger', 'calc_type', 'rate']]]);
});

test('POST commission-rules creates a rule', function () {
    $admin = commAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.commission-rules.store'), [
            'trigger'   => 'disbursement',
            'calc_type' => 'percentage',
            'rate'      => 1.5,
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.rule.calc_type', 'percentage');
});

test('GET commissions/users/{userId}/summary returns summary', function () {
    $admin   = commAdmin();
    $officer = commOfficer();

    $this->actingAs($admin)
        ->getJson(route('api.v1.commissions.summary', $officer->id) . '?period=2026-03')
        ->assertOk()
        ->assertJsonStructure(['data' => ['period', 'user_id', 'pending', 'approved', 'paid', 'total']]);
});

test('POST commissions/approve-period approves commissions', function () {
    $admin   = commAdmin();
    $officer = commOfficer();

    StaffCommission::create([
        'user_id' => $officer->id, 'trigger' => 'disbursement',
        'base_amount' => 5000, 'commission_amount' => 50,
        'status' => 'pending', 'period_month' => '2026-03-01',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.commissions.approve-period'), ['period' => '2026-03'])
        ->assertOk()
        ->assertJsonPath('data.approved', 1);
});

test('unauthenticated cannot access commissions', function () {
    $this->getJson(route('api.v1.commission-rules.index'))->assertStatus(401);
});
