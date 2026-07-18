<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\CommissionRule;
use App\Models\Tenant\StaffCommission;
use App\Services\CommissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommissionController extends BaseApiController
{
    public function __construct(private CommissionService $svc) {}

    // ── Commission Rules ─────────────────────────────────────────────────────

    public function rules(): JsonResponse
    {
        $rules = CommissionRule::with(['user:id,name', 'loanType:id,name'])->get();

        return $this->success($rules->map(fn ($r) => [
            'id' => $r->id,
            'user' => $r->user ? ['id' => $r->user->id, 'name' => $r->user->name] : null,
            'loan_type' => $r->loanType ? ['id' => $r->loanType->id, 'name' => $r->loanType->name] : null,
            'trigger' => $r->trigger,
            'calc_type' => $r->calc_type,
            'rate' => (float) $r->rate,
            'min_amount' => $r->min_amount ? (float) $r->min_amount : null,
            'max_amount' => $r->max_amount ? (float) $r->max_amount : null,
            'is_active' => $r->is_active,
            'notes' => $r->notes,
        ])->values());
    }

    public function storeRule(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'loan_type_id' => ['nullable', 'integer', 'exists:loan_types,id'],
            'trigger' => ['required', 'string', 'in:disbursement,repayment,loan_completion'],
            'calc_type' => ['required', 'string', 'in:percentage,flat'],
            'rate' => ['required', 'numeric', 'min:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule = CommissionRule::create($data);

        return $this->success(['rule' => $rule], 'Commission rule created.', 201);
    }

    public function updateRule(Request $request, CommissionRule $rule): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'loan_type_id' => ['nullable', 'integer', 'exists:loan_types,id'],
            'trigger' => ['sometimes', 'string', 'in:disbursement,repayment,loan_completion'],
            'calc_type' => ['sometimes', 'string', 'in:percentage,flat'],
            'rate' => ['sometimes', 'numeric', 'min:0'],
            'min_amount' => ['nullable', 'numeric', 'min:0'],
            'max_amount' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $rule->update($data);

        return $this->success(['rule' => $rule]);
    }

    public function destroyRule(CommissionRule $rule): JsonResponse
    {
        $rule->delete();

        return $this->success(null, 'Commission rule deleted.');
    }

    // ── Earned Commissions ───────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $query = StaffCommission::with(['user:id,name', 'loan:id,loan_number'])
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->period, fn ($q) => $q->where('period_month', 'like', $request->period.'%'))
            ->orderByDesc('created_at');

        return $this->paginated($query->paginate(50), fn ($c) => [
            'id' => $c->id,
            'user' => $c->user ? ['id' => $c->user->id, 'name' => $c->user->name] : null,
            'loan_number' => $c->loan?->loan_number,
            'trigger' => $c->trigger,
            'base_amount' => (float) $c->base_amount,
            'commission_amount' => (float) $c->commission_amount,
            'status' => $c->status,
            'period_month' => $c->period_month?->format('Y-m'),
        ]);
    }

    public function summary(Request $request, int $userId): JsonResponse
    {
        $period = $request->get('period', now()->format('Y-m'));

        return $this->success($this->svc->summary($userId, $period));
    }

    public function approvePeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $count = $this->svc->approvePeriod($data['period'], $request->user()->id);

        return $this->success(['approved' => $count], "{$count} commission(s) approved.");
    }

    public function markPaid(Request $request): JsonResponse
    {
        $data = $request->validate([
            'commission_ids' => ['required', 'array', 'min:1'],
            'commission_ids.*' => ['integer'],
        ]);

        $count = $this->svc->markPaid($data['commission_ids'], $request->user()->id);

        return $this->success(['paid' => $count], "{$count} commission(s) marked paid.");
    }
}
