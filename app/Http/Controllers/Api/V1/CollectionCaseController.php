<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\CollectionCase;
use App\Models\Tenant\EscalationRule;
use App\Models\Tenant\Loan;
use App\Services\CollectionAutomationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CollectionCaseController extends BaseApiController
{
    public function __construct(private CollectionAutomationService $service) {}

    // ─── Escalation Rules ─────────────────────────────────────────────────────

    public function rules(): JsonResponse
    {
        return $this->success(EscalationRule::orderBy('dpd_threshold')->get()->map(fn ($r) => [
            'id' => $r->id,
            'name' => $r->name,
            'dpd_threshold' => $r->dpd_threshold,
            'action' => $r->action,
            'assigned_to' => $r->assigned_to,
            'is_active' => $r->is_active,
            'sort_order' => $r->sort_order,
        ]));
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'dpd_threshold' => ['required', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['assign_collector', 'field_visit', 'legal_action', 'write_off_notice'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $rule = EscalationRule::create($data);

        return $this->success(['id' => $rule->id, 'name' => $rule->name], 'Escalation rule created.', 201);
    }

    public function updateRule(Request $request, EscalationRule $rule): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'dpd_threshold' => ['sometimes', 'integer', 'min:1'],
            'action' => ['sometimes', Rule::in(['assign_collector', 'field_visit', 'legal_action', 'write_off_notice'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $rule->update($data);

        return $this->success(['id' => $rule->id], 'Rule updated.');
    }

    public function destroyRule(EscalationRule $rule): JsonResponse
    {
        $rule->delete();

        return $this->success(null, 'Rule deleted.');
    }

    // ─── Collection Cases ─────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $cases = CollectionCase::with(['loan:id,loan_number,outstanding_balance', 'borrower:id,borrower_number,first_name,last_name', 'assignedTo:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->assigned_to, fn ($q, $u) => $q->where('assigned_to', $u))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return $this->paginated($cases, fn ($c) => $this->formatCase($c));
    }

    public function show(CollectionCase $collectionCase): JsonResponse
    {
        $collectionCase->load(['loan', 'borrower', 'assignedTo', 'escalationRule', 'promises']);

        return $this->success($this->formatCase($collectionCase));
    }

    public function update(Request $request, CollectionCase $collectionCase): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'promised', 'escalated', 'resolved', 'closed'])],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'])) {
            $data['resolved_at'] = now();
        }

        $collectionCase->update($data);

        return $this->success($this->formatCase($collectionCase->fresh()), 'Case updated.');
    }

    /** Manually trigger escalation for a specific loan. */
    public function escalateLoan(Loan $loan): JsonResponse
    {
        $action = $this->service->escalate($loan);

        if ($action === null) {
            return $this->success(['escalated' => false], 'No escalation rule matched or loan is not overdue.');
        }

        return $this->success(['escalated' => true, 'action' => $action], 'Loan escalated.');
    }

    // ─── Promise-to-Pay ───────────────────────────────────────────────────────

    public function promises(CollectionCase $collectionCase): JsonResponse
    {
        return $this->success($collectionCase->promises()->latest()->get()->map(fn ($p) => [
            'id' => $p->id,
            'promise_date' => $p->promise_date->toDateString(),
            'promise_amount' => (float) $p->promise_amount,
            'status' => $p->status,
            'notes' => $p->notes,
            'created_at' => $p->created_at->toIso8601String(),
        ]));
    }

    public function storePromise(Request $request, CollectionCase $collectionCase): JsonResponse
    {
        $data = $request->validate([
            'promise_date' => ['required', 'date', 'after:today'],
            'promise_amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $promise = $collectionCase->promises()->create([
            'loan_id' => $collectionCase->loan_id,
            'promise_date' => $data['promise_date'],
            'promise_amount' => $data['promise_amount'],
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        // Update case status to promised
        $collectionCase->update(['status' => 'promised']);

        return $this->success(['id' => $promise->id, 'status' => $promise->status], 'Promise recorded.', 201);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatCase(CollectionCase $c): array
    {
        return [
            'id' => $c->id,
            'status' => $c->status,
            'action_taken' => $c->action_taken,
            'dpd_at_creation' => $c->dpd_at_creation,
            'notes' => $c->notes,
            'resolved_at' => $c->resolved_at?->toIso8601String(),
            'created_at' => $c->created_at->toIso8601String(),
            'loan' => $c->relationLoaded('loan') && $c->loan ? [
                'id' => $c->loan->id,
                'loan_number' => $c->loan->loan_number,
                'outstanding_balance' => (float) $c->loan->outstanding_balance,
            ] : null,
            'outstanding_balance' => $c->relationLoaded('loan') && $c->loan ? (float) $c->loan->outstanding_balance : null,
            'borrower' => $c->relationLoaded('borrower') && $c->borrower ? [
                'id' => $c->borrower->id,
                'full_name' => $c->borrower->full_name,
            ] : null,
            'assigned_to' => $c->relationLoaded('assignedTo') ? $c->assignedTo?->name : null,
            'escalation_rule' => $c->relationLoaded('escalationRule') ? $c->escalationRule?->name : null,
            'promise_count' => $c->relationLoaded('promises') ? $c->promises->count() : null,
        ];
    }
}
