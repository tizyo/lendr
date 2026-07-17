<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function restructureAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function restructureLoan(array $attrs = []): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $staff    = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);

    return Loan::factory()->create(array_merge([
        'borrower_id'        => $borrower->id,
        'loan_type_id'       => $type->id,
        'loan_plan_id'       => $plan->id,
        'created_by'         => $staff->id,
        'status'             => LoanStatus::Active,
        'outstanding_balance' => 4000.00,
        'principal_amount'   => 5000.00,
        'tenure'             => 6,
    ], $attrs));
}

function seedSchedule(Loan $loan, int $count = 3): void
{
    for ($i = 1; $i <= $count; $i++) {
        LoanSchedule::create([
            'loan_id'           => $loan->id,
            'instalment_number' => $i,
            'due_date'          => now()->addMonths($i),
            'principal_due'     => 666.67,
            'interest_due'      => 200.00,
            'fee_due'           => 0,
            'total_due'         => 866.67,
            'outstanding'       => 4000.00 - ($i - 1) * 666.67,
            'is_paid'           => false,
        ]);
    }
}

// ─── Success Cases ────────────────────────────────────────────────────────────

test('can restructure an active loan', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();
    seedSchedule($loan, 3);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 12,
            'reason' => 'Borrower requested extension due to hardship.',
        ])
        ->assertOk()
        ->assertJsonPath('message', 'Loan restructured.');

    $loan->refresh();
    $this->assertSame(12, $loan->tenure);
})->group('restructure');

test('restructure replaces unpaid schedule entries', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();
    seedSchedule($loan, 4);

    $before = LoanSchedule::where('loan_id', $loan->id)->count();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 6,
            'reason' => 'New terms agreed.',
        ])
        ->assertOk();

    // Old unpaid entries deleted, 6 new rows created
    $after = LoanSchedule::where('loan_id', $loan->id)->count();
    $this->assertSame(6, $after);
})->group('restructure');

test('restructure preserves paid instalments in numbering', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();

    // 2 paid, 3 unpaid (unpaid start at instalment 3 to avoid unique constraint)
    LoanSchedule::create(['loan_id' => $loan->id, 'instalment_number' => 1, 'due_date' => now()->subMonths(2), 'principal_due' => 833, 'interest_due' => 250, 'fee_due' => 0, 'total_due' => 1083, 'outstanding' => 5000, 'is_paid' => true]);
    LoanSchedule::create(['loan_id' => $loan->id, 'instalment_number' => 2, 'due_date' => now()->subMonths(1), 'principal_due' => 833, 'interest_due' => 250, 'fee_due' => 0, 'total_due' => 1083, 'outstanding' => 4167, 'is_paid' => true]);
    // Add 3 unpaid starting from instalment 3
    for ($i = 3; $i <= 5; $i++) {
        LoanSchedule::create(['loan_id' => $loan->id, 'instalment_number' => $i, 'due_date' => now()->addMonths($i - 2), 'principal_due' => 666.67, 'interest_due' => 200, 'fee_due' => 0, 'total_due' => 866.67, 'outstanding' => 4000, 'is_paid' => false]);
    }

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 4,
            'reason' => 'Partial restructure.',
        ])
        ->assertOk();

    $minNew = LoanSchedule::where('loan_id', $loan->id)
        ->where('is_paid', false)
        ->min('instalment_number');

    $this->assertGreaterThan(2, $minNew);
})->group('restructure');

test('can restructure a frozen loan', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan(['status' => LoanStatus::Frozen]);
    seedSchedule($loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 6,
            'reason' => 'Account unfrozen and rescheduled.',
        ])
        ->assertOk();
})->group('restructure');

test('can restructure a defaulted loan', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan(['status' => LoanStatus::Defaulted]);
    seedSchedule($loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 8,
            'reason' => 'Negotiated repayment plan.',
        ])
        ->assertOk();
})->group('restructure');

test('restructure logs an activity record', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();
    seedSchedule($loan);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 9,
            'reason' => 'Audit trail test.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'description'  => 'restructured',
        'subject_id'   => $loan->id,
    ]);
})->group('restructure');

// ─── Validation ───────────────────────────────────────────────────────────────

test('restructure fails without tenure', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'reason' => 'Missing tenure.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tenure']);
})->group('restructure');

test('restructure fails without reason', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 6,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
})->group('restructure');

test('cannot restructure a submitted loan', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan(['status' => LoanStatus::Submitted]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 6,
            'reason' => 'Not allowed.',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only active, frozen, or defaulted loans can be restructured.');
})->group('restructure');

test('cannot restructure a completed loan', function () {
    $admin = restructureAdmin();
    $loan  = restructureLoan(['status' => LoanStatus::Completed]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.restructure', $loan), [
            'tenure' => 6,
            'reason' => 'Not allowed.',
        ])
        ->assertUnprocessable();
})->group('restructure');

test('unauthenticated request to restructure is rejected', function () {
    $loan = restructureLoan();

    $this->postJson(route('api.v1.loans.restructure', $loan), [
        'tenure' => 6,
        'reason' => 'Test',
    ])
        ->assertUnauthorized();
})->group('restructure');
