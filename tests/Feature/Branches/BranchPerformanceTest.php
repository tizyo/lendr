<?php

use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Branch;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use App\Services\BranchPerformanceService;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function bpAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function bpBranch(array $extra = []): Branch
{
    return Branch::create(array_merge([
        'name' => 'HQ Branch '.fake()->unique()->word(),
        'code' => strtoupper(fake()->unique()->lexify('???')),
        'is_active' => true,
    ], $extra));
}

function bpLoan(Branch $branch, User $officer, array $extra = []): Loan
{
    $borrower = Borrower::factory()->create(['is_active' => true]);
    $lt = LoanType::first() ?? LoanType::create(['name' => 'Personal', 'code' => 'PL', 'is_active' => true]);
    $plan = LoanPlan::where('loan_type_id', $lt->id)->first() ?? LoanPlan::create([
        'loan_type_id' => $lt->id,
        'name' => 'BP Plan',
        'code' => 'BP-'.uniqid(),
        'interest_rate' => 15,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'min_tenure' => 1,
        'max_tenure' => 24,
        'tenure_type' => 'months',
        'min_amount' => 500,
        'max_amount' => 100000,
        'penalty_rate' => 2,
        'penalty_type' => 'flat',
        'grace_period_days' => 0,
        'repayment_schedule' => 'monthly',
        'processing_fee' => 2,
        'insurance_fee' => 0,
        'is_active' => true,
    ]);

    return Loan::create(array_merge([
        'loan_number' => 'LN-BP-'.fake()->unique()->numerify('####'),
        'borrower_id' => $borrower->id,
        'loan_type_id' => $lt->id,
        'loan_plan_id' => $plan->id,
        'branch_id' => $branch->id,
        'created_by' => $officer->id,
        'principal_amount' => 10000,
        'interest_amount' => 1500,
        'processing_fee' => 200,
        'insurance_fee' => 0,
        'total_payable' => 11500,
        'total_paid' => 0,
        'outstanding_balance' => 11500,
        'penalty_balance' => 0,
        'interest_rate' => 15,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'tenure' => 12,
        'tenure_type' => 'months',
        'repayment_schedule' => 'monthly',
        'status' => 'disbursed',
        'application_date' => now()->toDateString(),
        'disbursement_date' => now()->toDateString(),
    ], $extra));
}

// ─── Service unit tests ───────────────────────────────────────────────────────

test('pnl returns zero data for empty branch', function () {
    $branch = bpBranch();
    $svc = app(BranchPerformanceService::class);
    $pnl = $svc->pnl($branch);

    expect($pnl['disbursements_count'])->toBe(0)
        ->and($pnl['disbursements_amount'])->toBe(0.0)
        ->and($pnl['net_income'])->toBe(0.0);
});

test('pnl aggregates disbursements and payments for branch', function () {
    $branch = bpBranch();
    $officer = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true, 'branch' => $branch->code]);
    $loan = bpLoan($branch, $officer);

    Payment::create([
        'loan_id' => $loan->id,
        'receipt_number' => 'REC-TEST-001',
        'amount' => 1000,
        'principal_allocated' => 800,
        'interest_allocated' => 200,
        'penalty_allocated' => 0,
        'fee_allocated' => 0,
        'payment_method' => 'cash',
        'payment_date' => now()->toDateString(),
        'source' => 'manual',
    ]);

    $svc = app(BranchPerformanceService::class);
    $pnl = $svc->pnl($branch);

    expect($pnl['disbursements_count'])->toBe(1)
        ->and($pnl['disbursements_amount'])->toBe(10000.0)
        ->and($pnl['interest_income'])->toBe(200.0)
        ->and($pnl['fee_income'])->toBe(200.0);  // processing_fee on loan
});

test('pnl filters by period correctly', function () {
    $branch = bpBranch();
    $officer = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
    bpLoan($branch, $officer, ['disbursement_date' => '2025-01-15']);
    bpLoan($branch, $officer, ['disbursement_date' => '2026-03-10']);

    $svc = app(BranchPerformanceService::class);

    $jan2025 = $svc->pnl($branch, '2025-01');
    $mar2026 = $svc->pnl($branch, '2026-03');

    expect($jan2025['disbursements_count'])->toBe(1)
        ->and($mar2026['disbursements_count'])->toBe(1);
});

test('portfolioHealth returns active loan metrics', function () {
    $branch = bpBranch();
    $officer = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);
    bpLoan($branch, $officer);

    $svc = app(BranchPerformanceService::class);
    $health = $svc->portfolioHealth($branch);

    expect($health['total_active_loans'])->toBe(1)
        ->and($health['total_outstanding'])->toBe(11500.0);
});

test('officerLeague ranks officers by disbursements', function () {
    $branch = bpBranch();
    $officer1 = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true, 'branch' => $branch->code]);
    $officer2 = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true, 'branch' => $branch->code]);

    bpLoan($branch, $officer1, ['principal_amount' => 20000, 'outstanding_balance' => 20000, 'total_payable' => 20000]);
    bpLoan($branch, $officer2, ['principal_amount' => 5000,  'outstanding_balance' => 5000,  'total_payable' => 5000]);

    $svc = app(BranchPerformanceService::class);
    $league = $svc->officerLeague($branch);

    expect($league)->toHaveCount(2)
        ->and($league[0]['user_id'])->toBe($officer1->id)    // highest disbursements first
        ->and($league[1]['user_id'])->toBe($officer2->id);
});

// ─── API endpoint tests ───────────────────────────────────────────────────────

test('GET branches/{branch}/performance/pnl returns pnl data', function () {
    $admin = bpAdmin();
    $branch = bpBranch();

    $this->actingAs($admin)
        ->getJson(route('api.v1.branches.performance.pnl', $branch))
        ->assertOk()
        ->assertJsonPath('data.branch.id', $branch->id)
        ->assertJsonStructure(['data' => ['branch', 'pnl' => ['disbursements_count', 'disbursements_amount', 'net_income']]]);
});

test('GET branches/{branch}/performance/pnl validates period format', function () {
    $admin = bpAdmin();
    $branch = bpBranch();

    $this->actingAs($admin)
        ->getJson(route('api.v1.branches.performance.pnl', $branch).'?period=bad-format')
        ->assertStatus(422);
});

test('GET branches/{branch}/performance/portfolio returns portfolio health', function () {
    $admin = bpAdmin();
    $branch = bpBranch();

    $this->actingAs($admin)
        ->getJson(route('api.v1.branches.performance.portfolio', $branch))
        ->assertOk()
        ->assertJsonStructure(['data' => ['branch', 'portfolio' => ['total_active_loans', 'total_outstanding', 'npl_rate']]]);
});

test('GET branches/{branch}/performance/officers returns officer league', function () {
    $admin = bpAdmin();
    $branch = bpBranch();

    $this->actingAs($admin)
        ->getJson(route('api.v1.branches.performance.officers', $branch))
        ->assertOk()
        ->assertJsonStructure(['data' => ['branch', 'officers']]);
});

test('unauthenticated cannot access branch performance', function () {
    $branch = bpBranch();

    $this->getJson(route('api.v1.branches.performance.pnl', $branch))
        ->assertStatus(401);
});
