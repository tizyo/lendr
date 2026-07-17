<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanInterestAccrual;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function accrualAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function accrualLoan(float $balance = 10000, float $annualRate = 24.0, int $dpdDays = 0): Loan
{
    $type     = LoanType::first() ?? LoanType::factory()->create();
    $plan     = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id'         => $borrower->id,
        'loan_type_id'        => $type->id,
        'loan_plan_id'        => $plan->id,
        'status'              => LoanStatus::Active,
        'principal_amount'    => $balance,
        'outstanding_balance' => $balance,
        'interest_rate'       => $annualRate,
        'interest_type'       => 'flat',
        'interest_period'     => 'monthly',
    ]);

    if ($dpdDays > 0) {
        LoanSchedule::create([
            'loan_id'           => $loan->id,
            'instalment_number' => 1,
            'due_date'          => now()->subDays($dpdDays)->toDateString(),
            'principal_due'     => 1000,
            'interest_due'      => 100,
            'total_due'         => 1100,
            'outstanding'       => 1100,
            'is_paid'           => false,
        ]);
    }

    return $loan;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('can run interest accrual for a date', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.loans_processed'))->toBe(1)
        ->and($resp->json('data.total_accrued'))->toBeGreaterThan(0);
});

test('daily accrual amount is correct', function () {
    $admin = accrualAdmin();
    accrualLoan(36500, 36.5); // 36500 * 36.5% / 365 = exactly 36.50 per day

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect((float) $resp->json('data.total_accrued'))->toBe(36.5);
});

test('accrual is skipped for zero-balance loans', function () {
    $admin = accrualAdmin();
    accrualLoan(0); // no outstanding balance

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.loans_processed'))->toBe(0);
});

test('stage 3 non-performing loans are suspended from accrual', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0, 120); // 120 DPD → Stage 3

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.loans_suspended'))->toBe(1)
        ->and((float) $resp->json('data.total_accrued'))->toBe(0.0);

    $this->assertDatabaseHas('loan_interest_accruals', ['is_suspended' => 1, 'accrued_amount' => 0]);
});

test('duplicate accrual for same date is skipped', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);

    $date = now()->toDateString();
    $this->actingAs($admin)->postJson(route('api.v1.interest-accrual.run'), ['date' => $date]);
    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => $date])
        ->assertOk();

    expect($resp->json('data.skipped'))->toBe(1);
    expect(LoanInterestAccrual::count())->toBe(1);
});

test('dry run does not persist accrual records', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), [
            'date'    => now()->toDateString(),
            'dry_run' => true,
        ])
        ->assertOk();

    expect($resp->json('data.dry_run'))->toBeTrue();
    expect(LoanInterestAccrual::count())->toBe(0);
});

test('can list accruals', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);

    $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.interest-accrual.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

test('can filter accruals by loan', function () {
    $admin = accrualAdmin();
    $loanA = accrualLoan(10000, 24.0);
    $loanB = accrualLoan(5000, 18.0);

    $date = now()->toDateString();
    $this->actingAs($admin)->postJson(route('api.v1.interest-accrual.run'), ['date' => $date]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.interest-accrual.index') . '?loan_id=' . $loanA->id)
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1)
        ->and($resp->json('data.0.loan_id'))->toBe($loanA->id);
});

test('can get monthly accrual summary', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);

    $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.interest-accrual.summary') . '?year=' . now()->year)
        ->assertOk();

    expect($resp->json('data.annual_total'))->toBeGreaterThan(0)
        ->and($resp->json('data.months'))->not->toBeEmpty();
});

test('portfolio accrual covers multiple loans', function () {
    $admin = accrualAdmin();
    accrualLoan(10000, 24.0);
    accrualLoan(20000, 18.0);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.interest-accrual.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.loans_processed'))->toBe(2)
        ->and($resp->json('data.total_accrued'))->toBeGreaterThan(0);
});

test('unauthenticated cannot access accrual endpoints', function () {
    $this->postJson(route('api.v1.interest-accrual.run'))
        ->assertUnauthorized();
});
