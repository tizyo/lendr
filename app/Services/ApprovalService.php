<?php

namespace App\Services;

use App\Exceptions\LoanActionException;
use App\Models\Tenant\ApprovalAction;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApprovalService
{
    /**
     * Submit an entity for approval.
     * Finds the matching workflow; returns null if no workflow applies.
     */
    public function submit(
        string $entityType,
        int $entityId,
        User $submitter,
        float $amount = 0,
        ?string $notes = null,
    ): ?ApprovalRequest {
        $entityType = ApprovalWorkflow::normaliseType($entityType);
        $workflow = ApprovalWorkflow::findFor($entityType, $amount);

        if (! $workflow) {
            return null;
        }

        // One open request per entity: re-submitting returns the one already waiting.
        $pending = ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'pending')
            ->first();

        return $pending ?? ApprovalRequest::create([
            'workflow_id' => $workflow->id,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'submitted_by' => $submitter->id,
            'status' => 'pending',
            'notes' => $notes,
        ]);
    }

    /**
     * Gate an action behind its approval workflow (loan approval, disbursement,
     * expense, write-off). Passes when no workflow covers this amount, or when a
     * request for this entity has been approved. Otherwise submits one (if none is
     * waiting) and refuses the action until it is approved.
     *
     * @throws LoanActionException
     */
    public function ensureApproved(string $entityType, int $entityId, User $submitter, float $amount, string $what): void
    {
        $entityType = ApprovalWorkflow::normaliseType($entityType);

        if (! ApprovalWorkflow::findFor($entityType, $amount)) {
            return;
        }

        $approved = ApprovalRequest::where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', 'approved')
            ->exists();

        if ($approved) {
            return;
        }

        $this->submit($entityType, $entityId, $submitter, $amount);

        throw LoanActionException::approvalRequired(
            "Approval required before {$what}. A request has been sent to the approvers (Loans → Approvals).",
        );
    }

    /**
     * Record an approval action; approve the request once enough different people
     * holding a required role have approved it.
     *
     * @throws ValidationException when this person may not approve this request
     */
    public function approve(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $request = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                return $request->fresh('actions', 'workflow');
            }

            $this->assertMayAct($request, $actor);

            ApprovalAction::create([
                'request_id' => $request->id,
                'actor_id' => $actor->id,
                'action' => 'approve',
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            // Distinct people: one person approving twice is still one approval.
            $approvers = $request->actions()->where('action', 'approve')->distinct()->count('actor_id');

            if ($approvers >= max(1, (int) $request->workflow->required_approvals)) {
                $request->update(['status' => 'approved', 'decided_at' => now()]);
            }

            return $request->fresh('actions', 'workflow');
        });
    }

    /**
     * Reject a request. One rejection is enough.
     *
     * @throws ValidationException when this person may not decide this request
     */
    public function reject(ApprovalRequest $request, User $actor, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $actor, $notes) {
            $request = ApprovalRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($request->status !== 'pending') {
                return $request->fresh('actions');
            }

            $this->assertMayAct($request, $actor, rejecting: true);

            ApprovalAction::create([
                'request_id' => $request->id,
                'actor_id' => $actor->id,
                'action' => 'reject',
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            $request->update(['status' => 'rejected', 'decided_at' => now()]);

            return $request->fresh('actions');
        });
    }

    /**
     * All pending requests, optionally filtered by entity type.
     */
    public function pending(?string $entityType = null)
    {
        return ApprovalRequest::with(['workflow', 'submittedBy', 'actions'])
            ->where('status', 'pending')
            ->when($entityType, fn ($q) => $q->where('entity_type', ApprovalWorkflow::normaliseType($entityType)))
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Maker-checker: the person who asked can't approve their own request, each
     * approver counts once, and only the workflow's roles may decide.
     */
    private function assertMayAct(ApprovalRequest $request, User $actor, bool $rejecting = false): void
    {
        if (! $rejecting && (int) $request->submitted_by === (int) $actor->id) {
            throw ValidationException::withMessages(['approval' => 'You submitted this request, so someone else has to approve it.']);
        }

        if (! $rejecting && $request->actions()->where('actor_id', $actor->id)->where('action', 'approve')->exists()) {
            throw ValidationException::withMessages(['approval' => 'You have already approved this request. It needs another approver.']);
        }

        $roles = array_filter((array) ($request->workflow?->required_roles ?? []));
        if ($roles && ! $actor->hasAnyRole($roles)) {
            $names = collect($roles)->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->implode(', ');
            throw ValidationException::withMessages(['approval' => "Only these roles can decide this request: {$names}."]);
        }
    }
}
