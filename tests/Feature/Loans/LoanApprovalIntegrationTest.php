<?php

use App\Enums\LoanStatus;
use App\Enums\UserRole;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\User;
use Spatie\Permission\Models\Permission;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function disbApprovalAdmin(): User
{
    $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    Permission::firstOrCreate(['name' => 'loans.disburse', 'guard_name' => 'web']);
    $user->givePermissionTo('loans.disburse');
    return $user;
}

function disbApprovalLoan(float $amount = 10000): Loan
{
    $type     = LoanType::factory()->create();
    $plan     = LoanPlan::factory()->create([
        'loan_type_id'   => $type->id,
        'min_amount'     => 1000,
        'max_amount'     => 100000,
        'interest_rate'  => 24,
        'interest_type'  => 'flat',
        'interest_period' => 'monthly',
    ]);
    $borrower = Borrower::factory()->create();

    return Loan::factory()->create([
        'borrower_id'      => $borrower->id,
        'loan_type_id'     => $type->id,
        'loan_plan_id'     => $plan->id,
        'status'           => LoanStatus::Approved,
        'principal_amount' => $amount,
        'outstanding_balance' => $amount,
        'interest_rate'    => 24,
        'interest_type'    => 'flat',
        'interest_period'  => 'monthly',
        'tenure'           => 3,
        'tenure_type'      => 'months',
        'application_date' => now()->toDateString(),
    ]);
}

function disbApprovalWorkflow(float $minAmount = 0): ApprovalWorkflow
{
    return ApprovalWorkflow::create([
        'name'               => 'Loan Disbursement Approval',
        'entity_type'        => 'loan_disbursement',
        'min_amount'         => $minAmount,
        'max_amount'         => null,
        'required_roles'     => ['SuperAdmin'],
        'required_approvals' => 1,
        'is_active'          => true,
    ]);
}

// ─── Tests ────────────────────────────────────────────────────────────────────

test('disbursement proceeds normally when no approval workflow exists', function () {
    $admin = disbApprovalAdmin();
    $loan  = disbApprovalLoan(5000);

    // No workflow registered — disbursement should proceed
    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_date'   => now()->toDateString(),
            'disbursement_method' => 'cash',
        ])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('active');
});

test('disbursement blocked when approval workflow exists and no approved request', function () {
    $admin    = disbApprovalAdmin();
    $loan     = disbApprovalLoan(50000);
    $workflow = disbApprovalWorkflow(10000); // requires approval for loans >= 10000

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_date'   => now()->toDateString(),
            'disbursement_method' => 'cash',
        ])
        ->assertStatus(422);

    expect($resp->json('message'))->toContain('Approval');
    // Approval request auto-submitted
    expect(ApprovalRequest::where('entity_type', 'loan_disbursement')->where('entity_id', $loan->id)->count())->toBe(1);
});

test('second disbursement attempt reuses existing pending request', function () {
    $admin    = disbApprovalAdmin();
    $loan     = disbApprovalLoan(50000);
    $workflow = disbApprovalWorkflow(10000);

    // First attempt — creates request
    $this->actingAs($admin)->postJson(route('api.v1.loans.disburse', $loan), [
        'disbursement_date'   => now()->toDateString(),
        'disbursement_method' => 'cash',
    ]);

    // Second attempt — should not create a duplicate
    $this->actingAs($admin)->postJson(route('api.v1.loans.disburse', $loan), [
        'disbursement_date'   => now()->toDateString(),
        'disbursement_method' => 'cash',
    ]);

    expect(ApprovalRequest::where('entity_type', 'loan_disbursement')->where('entity_id', $loan->id)->count())->toBe(1);
});

test('disbursement succeeds after approval request is approved', function () {
    $admin    = disbApprovalAdmin();
    $approver = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    $loan     = disbApprovalLoan(50000);
    $workflow = disbApprovalWorkflow(10000);

    // Submit approval request
    $approvalRequest = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => $loan->id,
        'submitted_by' => $admin->id,
        'status'       => 'approved',
        'decided_at'   => now(),
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.disburse', $loan), [
            'disbursement_date'   => now()->toDateString(),
            'disbursement_method' => 'cash',
        ])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('active');
});

test('disbursement blocked for amount above threshold, not below', function () {
    $admin    = disbApprovalAdmin();
    $workflow = disbApprovalWorkflow(100000); // only for amounts >= 100000

    // Small loan — no workflow applies
    $smallLoan = disbApprovalLoan(5000);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.loans.disburse', $smallLoan), [
            'disbursement_date'   => now()->toDateString(),
            'disbursement_method' => 'cash',
        ])
        ->assertOk();

    expect($resp->json('data.status'))->toBe('active');
    expect(ApprovalRequest::count())->toBe(0);
});
