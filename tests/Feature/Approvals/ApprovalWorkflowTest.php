<?php

use App\Enums\UserRole;
use App\Models\Tenant\ApprovalAction;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\User;

// ─── Helpers ──────────────────────────────────────────────────────────────────

function approvalAdmin(): User
{
    return User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
}

function makeWorkflow(array $overrides = []): ApprovalWorkflow
{
    return ApprovalWorkflow::create(array_merge([
        'name'               => 'Loan Disbursement Approval',
        'entity_type'        => 'loan_disbursement',
        'min_amount'         => 0,
        'max_amount'         => null,
        'required_roles'     => ['BranchManager', 'SuperAdmin'],
        'required_approvals' => 1,
        'is_active'          => true,
    ], $overrides));
}

// ─── Workflow Management ──────────────────────────────────────────────────────

test('can create an approval workflow', function () {
    $admin = approvalAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.workflows.store'), [
            'name'               => 'High-Value Disbursement',
            'entity_type'        => 'loan_disbursement',
            'min_amount'         => 50000,
            'required_roles'     => ['SuperAdmin'],
            'required_approvals' => 2,
        ])
        ->assertStatus(201);

    expect($resp->json('data.workflow.name'))->toBe('High-Value Disbursement')
        ->and($resp->json('data.workflow.required_approvals'))->toBe(2);
});

test('can list approval workflows', function () {
    $admin = approvalAdmin();
    makeWorkflow();
    makeWorkflow(['name' => 'Expense Approval', 'entity_type' => 'expense']);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.approvals.workflows.index'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(2);
});

test('can update a workflow', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    $resp = $this->actingAs($admin)
        ->putJson(route('api.v1.approvals.workflows.update', $workflow), [
            'required_approvals' => 2,
            'is_active'          => false,
        ])
        ->assertOk();

    expect($resp->json('data.workflow.required_approvals'))->toBe(2)
        ->and($resp->json('data.workflow.is_active'))->toBe(false);
});

// ─── Submit Request ───────────────────────────────────────────────────────────

test('can submit an approval request', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.submit'), [
            'entity_type' => 'loan_disbursement',
            'entity_id'   => 1,
            'amount'      => 10000,
        ])
        ->assertStatus(201);

    expect($resp->json('data.request.status'))->toBe('pending')
        ->and($resp->json('data.request.workflow_id'))->toBe($workflow->id);

    expect(ApprovalRequest::count())->toBe(1);
});

test('submit returns null data when no workflow matches', function () {
    $admin = approvalAdmin();

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.submit'), [
            'entity_type' => 'nonexistent_type',
            'entity_id'   => 99,
        ])
        ->assertOk();

    expect($resp->json('data'))->toBeNull();
    expect(ApprovalRequest::count())->toBe(0);
});

// ─── Pending List ─────────────────────────────────────────────────────────────

test('can list pending approval requests', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.approvals.pending'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(1);
});

test('approved requests do not appear in pending list', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'approved',
        'decided_at'   => now(),
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.approvals.pending'))
        ->assertOk();

    expect($resp->json('data'))->toHaveCount(0);
});

// ─── Show ─────────────────────────────────────────────────────────────────────

test('can show an approval request', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    $request = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 5,
        'submitted_by' => $admin->id,
        'status'       => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->getJson(route('api.v1.approvals.show', $request))
        ->assertOk();

    expect($resp->json('data.entity_id'))->toBe(5);
});

// ─── Approve ──────────────────────────────────────────────────────────────────

test('can approve a request and it becomes approved when threshold reached', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow(['required_approvals' => 1]);

    $request = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.approve', $request), ['notes' => 'Looks good'])
        ->assertOk();

    expect($resp->json('data.request.status'))->toBe('approved');
    expect(ApprovalAction::count())->toBe(1);
});

test('request stays pending until required_approvals threshold is met', function () {
    $admin    = approvalAdmin();
    $approver = User::factory()->create(['role' => UserRole::SuperAdmin, 'is_active' => true]);
    $workflow = makeWorkflow(['required_approvals' => 2]);

    $request = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'pending',
    ]);

    // First approval — still pending
    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.approve', $request), [])
        ->assertOk();

    expect($resp->json('data.request.status'))->toBe('pending');

    // Second approval — now approved
    $resp = $this->actingAs($approver)
        ->postJson(route('api.v1.approvals.approve', $request), [])
        ->assertOk();

    expect($resp->json('data.request.status'))->toBe('approved');
});

// ─── Reject ───────────────────────────────────────────────────────────────────

test('can reject a request', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    $request = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'pending',
    ]);

    $resp = $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.reject', $request), ['notes' => 'Insufficient collateral'])
        ->assertOk();

    expect($resp->json('data.request.status'))->toBe('rejected');
});

test('already-decided request ignores further actions', function () {
    $admin    = approvalAdmin();
    $workflow = makeWorkflow();

    $request = ApprovalRequest::create([
        'workflow_id'  => $workflow->id,
        'entity_type'  => 'loan_disbursement',
        'entity_id'    => 1,
        'submitted_by' => $admin->id,
        'status'       => 'rejected',
        'decided_at'   => now(),
    ]);

    $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.approve', $request))
        ->assertOk();

    // Status must NOT change to approved
    expect(ApprovalRequest::find($request->id)->status)->toBe('rejected');
    expect(ApprovalAction::count())->toBe(0);
});

// ─── Validation ───────────────────────────────────────────────────────────────

test('workflow store requires name entity_type and required_roles', function () {
    $admin = approvalAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.v1.approvals.workflows.store'), [])
        ->assertUnprocessable();
});

test('unauthenticated cannot access approval endpoints', function () {
    $this->getJson(route('api.v1.approvals.workflows.index'))->assertUnauthorized();
    $this->getJson(route('api.v1.approvals.pending'))->assertUnauthorized();
    $this->postJson(route('api.v1.approvals.submit'))->assertUnauthorized();
});
