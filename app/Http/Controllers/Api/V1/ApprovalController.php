<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Loan;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        if ($request->filled('entity_type')) {
            $request->merge(['entity_type' => ApprovalWorkflow::normaliseType((string) $request->input('entity_type'))]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'entity_type' => ['required', Rule::in(array_keys(ApprovalWorkflow::TYPES))],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'required_roles' => ['required', 'array', 'min:1'],
            'required_roles.*' => ['string', Rule::in(array_column(UserRole::cases(), 'value'))],
            'required_approvals' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ]);

        $workflow = ApprovalWorkflow::create($data + ['required_approvals' => $data['required_approvals'] ?? 1]);

        return $this->success(['workflow' => $this->formatWorkflow($workflow)], 'Workflow created.', 201);
    }

    public function updateWorkflow(Request $request, ApprovalWorkflow $workflow): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:200'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0', 'gte:min_amount'],
            'required_roles' => ['sometimes', 'array', 'min:1'],
            'required_roles.*' => ['string', Rule::in(array_column(UserRole::cases(), 'value'))],
            'required_approvals' => ['sometimes', 'integer', 'min:1'],
            'is_active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
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
        if ($request->filled('entity_type')) {
            $request->merge(['entity_type' => ApprovalWorkflow::normaliseType((string) $request->input('entity_type'))]);
        }

        $data = $request->validate([
            'entity_type' => ['required', Rule::in(array_keys(ApprovalWorkflow::TYPES))],
            'entity_id' => ['required', 'integer'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $approvalRequest = $this->service->submit(
            $data['entity_type'],
            $data['entity_id'],
            $request->user(),
            (float) ($data['amount'] ?? 0),
            $data['notes'] ?? null,
        );

        if (! $approvalRequest) {
            return $this->success(null, 'No matching workflow found for this entity.');
        }

        return $this->success(
            ['request' => $this->formatRequest($approvalRequest->load('workflow', 'submittedBy'))],
            'Approval request submitted.',
            201,
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
            'id' => $w->id,
            'name' => $w->name,
            'entity_type' => ApprovalWorkflow::normaliseType($w->entity_type),
            'entity_label' => ApprovalWorkflow::TYPES[ApprovalWorkflow::normaliseType($w->entity_type)] ?? $w->entity_type,
            'min_amount' => (float) $w->min_amount,
            'max_amount' => $w->max_amount ? (float) $w->max_amount : null,
            'required_roles' => $w->required_roles,
            'required_approvals' => $w->required_approvals,
            'is_active' => $w->is_active,
            'description' => $w->description,
        ];
    }

    /** What the request is about, in words, with a link to it. */
    private function subject(ApprovalRequest $r): array
    {
        $type = ApprovalWorkflow::normaliseType($r->entity_type);

        if (in_array($type, ['loan_approval', 'loan_disbursement', 'write_off'], true)) {
            $loan = Loan::with('borrower')->find($r->entity_id);

            return $loan ? [
                'title' => "{$loan->loan_number} · ".($loan->borrower?->full_name ?? 'Borrower'),
                'amount' => (float) ($type === 'write_off' ? $loan->outstanding_balance : $loan->principal_amount),
                'url' => route('loans.show', $loan->id),
            ] : ['title' => "Loan #{$r->entity_id} (deleted)", 'amount' => null, 'url' => null];
        }

        if ($type === 'expense') {
            $expense = Expense::find($r->entity_id);

            return $expense ? [
                'title' => trim(($expense->expense_number ?? "Expense #{$expense->id}").' · '.($expense->description ?? '')),
                'amount' => (float) $expense->amount,
                'url' => route('expenses.show', $expense->id),
            ] : ['title' => "Expense #{$r->entity_id} (deleted)", 'amount' => null, 'url' => null];
        }

        return ['title' => "{$r->entity_type} #{$r->entity_id}", 'amount' => null, 'url' => null];
    }

    private function formatRequest(ApprovalRequest $r): array
    {
        $type = ApprovalWorkflow::normaliseType($r->entity_type);

        return [
            'id' => $r->id,
            'workflow_id' => $r->workflow_id,
            'entity_type' => $type,
            'entity_label' => ApprovalWorkflow::TYPES[$type] ?? $r->entity_type,
            'entity_id' => $r->entity_id,
            'subject' => $this->subject($r),
            'required_approvals' => $r->workflow?->required_approvals,
            'required_roles' => $r->workflow?->required_roles ?? [],
            'submitted_by' => $r->submitted_by,
            'submitted_by_name' => $r->relationLoaded('submittedBy') ? $r->submittedBy?->name : null,
            'status' => $r->status,
            'notes' => $r->notes,
            'decided_at' => $r->decided_at?->toDateTimeString(),
            'created_at' => $r->created_at?->toDateTimeString(),
            'actions' => $r->relationLoaded('actions')
                ? $r->actions->map(fn ($a) => [
                    'id' => $a->id,
                    'actor_id' => $a->actor_id,
                    'action' => $a->action,
                    'notes' => $a->notes,
                    'acted_at' => $a->acted_at?->toDateTimeString(),
                ])->toArray()
                : [],
        ];
    }
}
