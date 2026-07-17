<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanTopup;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;

// ─── Helpers ─────────────────────────────────────────────────────────────────

function topupAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function topupLoan(array $attrs = []): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create(['loan_type_id' => $type->id]);
    $borrower = Borrower::factory()->create();
    $staff    = User::factory()->create(['role' => UserRole::LoanOfficer, 'is_active' => true]);

    return Loan::factory()->create(array_merge([
        'borrower_id'         => $borrower->id,
        'loan_type_id'        => $type->id,
        'loan_plan_id'        => $plan->id,
        'created_by'          => $staff->id,
        'status'              => LoanStatus::Active,
        'outstanding_balance' => 4000.00,
        'principal_amount'    => 5000.00,
        'total_paid'          => 1000.00,
        'total_payable'       => 6000.00,
        'interest_amount'     => 1000.00,
        'tenure'              => 6,
    ], $attrs));
}

function seedTopupSchedule(Loan $loan, int $count = 4, int $paid = 1): void
{
    for ($i = 1; $i <= $count; $i++) {
        LoanSchedule::create([
            'loan_id'           => $loan->id,
            'instalment_number' => $i,
            'due_date'          => now()->addMonths($i - $paid),
            'principal_due'     => 833.33,
            'interest_due'      => 166.67,
            'fee_due'           => 0,
            'total_due'         => 1000.00,
            'outstanding'       => 4000.00,
            'is_paid'           => $i <= $paid,
        ]);
    }
}

// ─── Submit (store) ───────────────────────────────────────────────────────────

test('can submit a top-up request for an active loan', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.store', $loan), [
            'topup_amount' => 2000,
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.topup_amount', fn ($v) => (float) $v === 2000.0);
})->group('topup');

test('can submit a top-up request for a disbursed loan', function () {
    $admin = topupAdmin();
    $loan  = topupLoan(['status' => LoanStatus::Disbursed]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.store', $loan), [
            'topup_amount' => 1500,
            'new_tenure'   => 8,
            'notes'        => 'Business expansion.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.new_tenure', 8);
})->group('topup');

test('cannot submit a top-up for a submitted loan', function () {
    $admin = topupAdmin();
    $loan  = topupLoan(['status' => LoanStatus::Submitted]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.store', $loan), [
            'topup_amount' => 2000,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only active or disbursed loans can receive top-ups.');
})->group('topup');

test('cannot submit a duplicate pending top-up', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1000,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.store', $loan), [
            'topup_amount' => 2000,
        ])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'A pending top-up request already exists for this loan.');
})->group('topup');

test('top-up store validates topup_amount is required', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.store', $loan), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['topup_amount']);
})->group('topup');

// ─── Index ────────────────────────────────────────────────────────────────────

test('can list top-ups for a loan', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    LoanTopup::create(['loan_id' => $loan->id, 'requested_by' => $admin->id, 'topup_amount' => 1000, 'status' => 'pending']);
    LoanTopup::create(['loan_id' => $loan->id, 'requested_by' => $admin->id, 'topup_amount' => 500,  'status' => 'approved', 'approved_by' => $admin->id, 'approved_at' => now()]);

    $this->actingAs($admin)
        ->getJson(route('api.v1.loans.topups.index', $loan))
        ->assertOk()
        ->assertJsonCount(2, 'data');
})->group('topup');

// ─── Approve ─────────────────────────────────────────────────────────────────

test('approving a top-up increases principal and regenerates schedule', function () {
    $admin = topupAdmin();
    $loan  = topupLoan(['outstanding_balance' => 4000.00, 'principal_amount' => 5000.00]);
    seedTopupSchedule($loan, 4, 1);

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 2000,
        'status'       => 'pending',
    ]);

    $unpaidBefore = LoanSchedule::where('loan_id', $loan->id)->where('is_paid', false)->count();

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.approve', ['loan' => $loan->id, 'topup' => $topup->id]))
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $loan->refresh();
    // Principal increased by topup_amount
    $this->assertEquals('7000.00', $loan->principal_amount);

    // Schedule regenerated (old unpaid rows removed, new rows inserted)
    $unpaidAfter = LoanSchedule::where('loan_id', $loan->id)->where('is_paid', false)->count();
    $this->assertGreaterThan(0, $unpaidAfter);
    $this->assertNotEquals($unpaidBefore, $unpaidAfter);
})->group('topup');

test('approving a top-up logs activity', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();
    seedTopupSchedule($loan);

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1000,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.approve', ['loan' => $loan->id, 'topup' => $topup->id]))
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'topup_approved',
        'subject_id'  => $loan->id,
    ]);
})->group('topup');

test('cannot approve an already approved top-up', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1000,
        'status'       => 'approved',
        'approved_by'  => $admin->id,
        'approved_at'  => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.approve', ['loan' => $loan->id, 'topup' => $topup->id]))
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Only pending top-ups can be approved.');
})->group('topup');

// ─── Reject ───────────────────────────────────────────────────────────────────

test('can reject a pending top-up with a reason', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 3000,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.reject', ['loan' => $loan->id, 'topup' => $topup->id]), [
            'reason' => 'Insufficient income to support additional debt.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseHas('loan_topups', [
        'id'               => $topup->id,
        'status'           => 'rejected',
        'rejection_reason' => 'Insufficient income to support additional debt.',
    ]);
})->group('topup');

test('reject requires a reason', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1000,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.reject', ['loan' => $loan->id, 'topup' => $topup->id]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reason']);
})->group('topup');

test('reject logs activity', function () {
    $admin = topupAdmin();
    $loan  = topupLoan();

    $topup = LoanTopup::create([
        'loan_id'      => $loan->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1500,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.reject', ['loan' => $loan->id, 'topup' => $topup->id]), [
            'reason' => 'Rejected for testing.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'description' => 'topup_rejected',
        'subject_id'  => $loan->id,
    ]);
})->group('topup');

// ─── Security ─────────────────────────────────────────────────────────────────

test('unauthenticated requests are rejected', function () {
    $loan = topupLoan();

    $this->postJson(route('api.v1.loans.topups.store', $loan), ['topup_amount' => 1000])
        ->assertUnauthorized();
})->group('topup');

test('topup belonging to different loan returns 404', function () {
    $admin  = topupAdmin();
    $loan1  = topupLoan();
    $loan2  = topupLoan();

    $topup = LoanTopup::create([
        'loan_id'      => $loan2->id,
        'requested_by' => $admin->id,
        'topup_amount' => 1000,
        'status'       => 'pending',
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.loans.topups.approve', ['loan' => $loan1->id, 'topup' => $topup->id]))
        ->assertNotFound();
})->group('topup');
