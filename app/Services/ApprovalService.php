<?php

namespace App\Services;

use App\Models\Tenant\ApprovalAction;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\User;

class ApprovalService
{
    /**
     * Submit an entity for approval.
     * Finds the matching workflow; returns null if no workflow applies.
     */
    public function submit(
        string $entityType,
        int    $entityId,
        User   $submitter,
        float  $amount = 0,
        ?string $notes = null
    ): ?ApprovalRequest {
        $workflow = ApprovalWorkflow::findFor($entityType, $amount);

        if (! $workflow) {
            return null;
        }

        return ApprovalRequest::create([
            'workflow_id'  => $workflow->id,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'submitted_by' => $submitter->id,
            'status'       => 'pending',
            'notes'        => $notes,
        ]);
    }

    /**
     * Record an approval action; auto-approve when required approvals reached.
     */
    public function approve(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            return $request;
        }

        ApprovalAction::create([
            'request_id' => $request->id,
            'actor_id'   => $actor->id,
            'action'     => 'approve',
            'notes'      => $notes,
            'acted_at'   => now(),
        ]);

        $approvalCount = $request->actions()->where('action', 'approve')->count();

        if ($approvalCount >= $request->workflow->required_approvals) {
            $request->update(['status' => 'approved', 'decided_at' => now()]);
        }

        return $request->fresh('actions', 'workflow');
    }

    /**
     * Reject a request. One rejection is enough.
     */
    public function reject(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            return $request;
        }

        ApprovalAction::create([
            'request_id' => $request->id,
            'actor_id'   => $actor->id,
            'action'     => 'reject',
            'notes'      => $notes,
            'acted_at'   => now(),
        ]);

        $request->update(['status' => 'rejected', 'decided_at' => now()]);

        return $request->fresh('actions');
    }

    /**
     * All pending requests, optionally filtered by entity type.
     */
    public function pending(?string $entityType = null)
    {
        return ApprovalRequest::with(['workflow', 'submittedBy', 'actions'])
            ->where('status', 'pending')
            ->when($entityType, fn ($q) => $q->where('entity_type', $entityType))
            ->orderByDesc('created_at')
            ->get();
    }
}
