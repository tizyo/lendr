<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends BaseApiController
{
    public function __construct(private ApprovalService $service) {}

    // ─── Workflow CRUD ────────────────────────────────────────────────────────

    public function indexWorkflows(): JsonResponse
    {
        $workflows = ApprovalWorkflow::orderBy('entity_type')->orderBy('name')->get();

        return $this->success($workflows->map(fn ($w) => $this->formatWorkflow($w)));
    }

    public function storeWorkflow(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['required', 'string', 'max:200'],
            'entity_type'        => ['required', 'string', 'max:100'],
            'min_amount'         => ['nullable', 'numeric', 'min:0'],
            'max_amount'         => ['nullable', 'numeric', 'min:0'],
            'required_roles'     => ['required', 'array', 'min:1'],
            'required_roles.*'   => ['string'],
            'required_approvals' => ['nullable', 'integer', 'min:1'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $workflow = ApprovalWorkflow::create($data + ['required_approvals' => $data['required_approvals'] ?? 1]);

        return $this->success(['workflow' => $this->formatWorkflow($workflow)], 'Workflow created.', 201);
    }

    public function updateWorkflow(Request $request, ApprovalWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'name'               => ['sometimes', 'string', 'max:200'],
            'min_amount'         => ['nullable', 'numeric', 'min:0'],
            'max_amount'         => ['nullable', 'numeric', 'min:0'],
            'required_roles'     => ['sometimes', 'array', 'min:1'],
            'required_roles.*'   => ['string'],
            'required_approvals' => ['sometimes', 'integer', 'min:1'],
            'is_active'          => ['sometimes', 'boolean'],
            'description'        => ['nullable', 'string', 'max:500'],
        ]);

        $workflow->update($data);

        return $this->success(['workflow' => $this->formatWorkflow($workflow->fresh())], 'Workflow updated.');
    }

    // ─── Requests ─────────────────────────────────────────────────────────────

    public function pending(Request $request): JsonResponse
    {
        $requests = $this->service->pending($request->entity_type);

        return $this->success($requests->map(fn ($r) => $this->formatRequest($r)));
    }

    public function submit(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entity_type' => ['required', 'string'],
            'entity_id'   => ['required', 'integer'],
            'amount'      => ['nullable', 'numeric', 'min:0'],
            'notes'       => ['nullable', 'string', 'max:500'],
        ]);

        $approvalRequest = $this->service->submit(
            $data['entity_type'],
            $data['entity_id'],
            $request->user(),
            (float) ($data['amount'] ?? 0),
            $data['notes'] ?? null
        );

        if (! $approvalRequest) {
            return $this->success(null, 'No matching workflow found for this entity.');
        }

        return $this->success(
            ['request' => $this->formatRequest($approvalRequest->load('workflow', 'submittedBy'))],
            'Approval request submitted.',
            201
        );
    }

    public function show(ApprovalRequest $approvalRequest): JsonResponse
    {
        return $this->success($this->formatRequest($approvalRequest->load('workflow', 'submittedBy', 'actions.actor')));
    }

    public function approve(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $updated = $this->service->approve($approvalRequest, $request->user(), $data['notes'] ?? null);

        return $this->success(['request' => $this->formatRequest($updated)], 'Approval recorded.');
    }

    public function reject(Request $request, ApprovalRequest $approvalRequest): JsonResponse
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $updated = $this->service->reject($approvalRequest, $request->user(), $data['notes'] ?? null);

        return $this->success(['request' => $this->formatRequest($updated)], 'Request rejected.');
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatWorkflow(ApprovalWorkflow $w): array
    {
        return [
            'id'                 => $w->id,
            'name'               => $w->name,
            'entity_type'        => $w->entity_type,
            'min_amount'         => (float) $w->min_amount,
            'max_amount'         => $w->max_amount ? (float) $w->max_amount : null,
            'required_roles'     => $w->required_roles,
            'required_approvals' => $w->required_approvals,
            'is_active'          => $w->is_active,
            'description'        => $w->description,
        ];
    }

    private function formatRequest(ApprovalRequest $r): array
    {
        return [
            'id'           => $r->id,
            'workflow_id'  => $r->workflow_id,
            'entity_type'  => $r->entity_type,
            'entity_id'    => $r->entity_id,
            'submitted_by' => $r->submitted_by,
            'status'       => $r->status,
            'notes'        => $r->notes,
            'decided_at'   => $r->decided_at?->toDateTimeString(),
            'created_at'   => $r->created_at?->toDateTimeString(),
            'actions'      => $r->relationLoaded('actions')
                ? $r->actions->map(fn ($a) => [
                    'id'       => $a->id,
                    'actor_id' => $a->actor_id,
                    'action'   => $a->action,
                    'notes'    => $a->notes,
                    'acted_at' => $a->acted_at?->toDateTimeString(),
                ])->toArray()
                : [],
        ];
    }
}
