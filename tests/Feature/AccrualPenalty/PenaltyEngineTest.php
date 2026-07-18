<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPenalty;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function penaltyAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function penaltyLoan(float $penaltyRate = 2.0, int $dpdDays = 30): Loan
{
    $type = LoanType::first() ?? LoanType::factory()->create();
    $plan = LoanPlan::first() ?? LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();

    $loan = Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'principal_amount' => 10000,
        'outstanding_balance' => 10000,
        'interest_rate' => 24,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'penalty_rate' => $penaltyRate,
    ]);

    if ($dpdDays > 0) {
        LoanSchedule::create([
            'loan_id' => $loan->id,
            'instalment_number' => 1,
            'due_date' => now()->subDays($dpdDays)->toDateString(),
            'principal_due' => 1000,
            'interest_due' => 100,
            'total_due' => 1100,
            'outstanding' => 1100,
            'is_paid' => false,
        ]);
    }

    return $loan;
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('can run penalty application for a date', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.applied'))->toBe(1)
        ->and($resp->json('data.total_penalty'))->toBeGreaterThan(0);
});

test('penalty amount is calculated from penalty rate and overdue amount', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30); // 2% of 1100 = 22.00

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect((float) $resp->json('data.total_penalty'))->toBe(22.0);
});

test('no penalty applied to paid installments', function () {
    $admin = penaltyAdmin();
    $type = LoanType::factory()->create();
    $plan = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $loan = Loan::factory()->create([
        'borrower_id' => $borrower->id,
        'loan_type_id' => $type->id,
        'loan_plan_id' => $plan->id,
        'status' => LoanStatus::Active,
        'outstanding_balance' => 10000,
        'interest_rate' => 24,
        'interest_type' => 'flat',
        'interest_period' => 'monthly',
        'penalty_rate' => 2.0,
    ]);
    LoanSchedule::create([
        'loan_id' => $loan->id,
        'instalment_number' => 1,
        'due_date' => now()->subDays(30)->toDateString(),
        'principal_due' => 1000,
        'interest_due' => 100,
        'total_due' => 1100,
        'outstanding' => 0,
        'is_paid' => true,     // ← paid
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.applied'))->toBe(0);
});

test('dry run does not persist penalties', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);

    $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), [
            'date' => now()->toDateString(),
            'dry_run' => true,
        ])
        ->assertOk();

    expect(LoanPenalty::count())->toBe(0);
});

test('duplicate penalty for same schedule and date is skipped', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);

    $date = now()->toDateString();
    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => $date]);
    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), ['date' => $date])
        ->assertOk();

    expect($resp->json('data.skipped'))->toBe(1);
    expect(LoanPenalty::count())->toBe(1);
});

test('can list all penalties', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);

    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.penalties.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

test('can list penalties for a specific loan', function () {
    $admin = penaltyAdmin();
    $loan = penaltyLoan(2.0, 30);

    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.loans.penalties', $loan))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1)
        ->and($resp->json('data.0.loan_id'))->toBe($loan->id);
});

test('can waive a penalty fully', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);

    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()]);
    $penalty = LoanPenalty::first();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.waive', $penalty), [
            'amount' => $penalty->penalty_amount,
            'reason' => 'Goodwill waiver',
        ])
        ->assertOk();

    expect($resp->json('data.penalty.status'))->toBe('waived')
        ->and((float) $resp->json('data.penalty.waived_amount'))->toBe((float) $penalty->penalty_amount);
});

test('can waive a penalty partially', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30); // penalty = 22.00

    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()]);
    $penalty = LoanPenalty::first();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.waive', $penalty), [
            'amount' => 10.0,
            'reason' => 'Partial relief',
        ])
        ->assertOk();

    expect($resp->json('data.penalty.status'))->toBe('applied')
        ->and((float) $resp->json('data.penalty.waived_amount'))->toBe(10.0);
});

test('waiver requires amount and reason', function () {
    $admin = penaltyAdmin();
    penaltyLoan(2.0, 30);
    $this->actingAs($admin)->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()]);
    $penalty = LoanPenalty::first();

    $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.waive', $penalty), [])
        ->assertUnprocessable();
});

test('zero-penalty-rate loan produces no penalty record', function () {
    $admin = penaltyAdmin();
    penaltyLoan(0.0, 30); // 0% penalty rate

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.penalties.run'), ['date' => now()->toDateString()])
        ->assertOk();

    expect($resp->json('data.applied'))->toBe(0);
    expect(LoanPenalty::count())->toBe(0);
});

test('unauthenticated cannot access penalty endpoints', function () {
    $this->postJson(route('api.v1.penalties.run'))->assertUnauthorized();
});
