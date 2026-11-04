<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Exceptions\LoanActionException;
use App\Http\Requests\Api\V1\Loan\DisburseRequest;
use App\Http\Requests\Api\V1\Loan\StoreLoanRequest;
use App\Models\Landlord\RepoItem;
use App\Models\Tenant\CollateralItem;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPlan;
use App\Models\Tenant\LoanWriteoff;
use App\Services\ApprovalService;
use App\Services\CrbService;
use App\Services\FundService;
use App\Services\GlLedgerService;
use App\Services\LoanCalculatorService;
use App\Services\LoanLifecycleService;
use App\Services\NotificationService;
use App\Services\Payment\DisbursementOutcomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LoanController extends BaseApiController
{
    public function __construct(
        private LoanCalculatorService $calculator,
        private FundService $fund,
        private NotificationService $notifications,
        private ApprovalService $approvals,
        private CrbService $crb,
    ) {}

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $loans = Loan::query()
            ->with([
                'borrower:id,first_name,last_name,borrower_number,phone',
                'loanType:id,name',
                'loanPlan:id,name',
                'createdBy:id,name',
            ])
            ->when($request->borrower_id, fn ($q, $id) => $q->where('borrower_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->loan_type_id, fn ($q, $id) => $q->where('loan_type_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('loan_number', 'like', "%{$s}%")
                    ->orWhereHas('borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%"),
                    );
            }))
            ->when($request->date_from, fn ($q, $d) => $q->where('application_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('application_date', '<=', $d))
            ->latest('application_date')
            ->paginate(20);

        return $this->paginated($loans, fn ($l) => $this->formatLoan($l));
    }

    public function store(StoreLoanRequest $request): JsonResponse
    {
        $plan = LoanPlan::findOrFail($request->loan_plan_id);

        $amounts = $this->calculator->calculateAmounts(
            $plan,
            (float) $request->principal_amount,
            (int) $request->tenure,
        );

        $loan = DB::transaction(function () use ($request, $plan, $amounts) {
            $loan = Loan::create([
                ...$request->only([
                    'borrower_id', 'loan_type_id', 'loan_plan_id',
                    'principal_amount', 'tenure', 'loan_purpose',
                    'application_date', 'collateral_description',
                    'guarantor_name', 'guarantor_phone', 'guarantor_relationship', 'notes', 'agent_id',
                ]),
                'loan_number' => $this->generateLoanNumber(),
                'created_by' => auth()->id(),
                'interest_amount' => $amounts['interest_amount'],
                'processing_fee' => $amounts['processing_fee'],
                'insurance_fee' => $amounts['insurance_fee'],
                'total_payable' => $amounts['total_payable'],
                'outstanding_balance' => $amounts['total_payable'],
                // Snapshot plan terms
                'interest_rate' => $plan->interest_rate,
                'interest_type' => $plan->interest_type,
                'interest_period' => $plan->interest_period,
                'tenure_type' => $plan->tenure_type,
                'repayment_schedule' => $plan->repayment_schedule,
                'penalty_rate' => $plan->penalty_rate,
                'grace_period_days' => $plan->grace_period_days,
                'status' => LoanStatus::Submitted->value,
                'currency' => tenancy()->tenant?->currency ?? 'ZMW',
            ]);

            $this->logStatusChange($loan, null, LoanStatus::Submitted, auth()->id(), 'Loan application submitted.');

            return $loan;
        });

        $loan->load(['borrower:id,first_name,last_name,borrower_number', 'loanType:id,name', 'loanPlan:id,name']);

        $this->notifications->notifyRoles(
            ['loan_officer', 'branch_manager', 'super_admin'],
            'loan_submitted',
            "New loan application: {$loan->loan_number}",
            "{$loan->borrower->full_name} applied for ZMW ".number_format((float) $loan->principal_amount, 2),
            ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
        );

        return $this->success($this->formatLoan($loan, true), 'Loan application created.', 201);
    }

    public function show(Loan $loan): JsonResponse
    {
        $loan->load([
            'borrower:id,first_name,last_name,borrower_number,phone',
            'loanType:id,name,code',
            'loanPlan:id,name,code',
            'createdBy:id,name',
            'approvedBy:id,name',
            'disbursedBy:id,name',
            'schedule',
            'statusLogs.changedBy:id,name',
            'payments:id,loan_id,amount,payment_date,method,status,reference,recorded_by',
        ]);

        return $this->success($this->formatLoan($loan, true));
    }

    public function update(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->status !== LoanStatus::Submitted && $loan->status !== LoanStatus::Draft) {
            return $this->error('Only draft or submitted loans can be edited.', 422);
        }

        $request->validate([
            'loan_purpose' => ['nullable', 'string', 'max:1000'],
            'collateral_description' => ['nullable', 'string', 'max:1000'],
            'guarantor_name' => ['nullable', 'string', 'max:150'],
            'guarantor_phone' => ['nullable', 'string', 'max:20'],
            'guarantor_relationship' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $loan->update($request->only(['loan_purpose', 'collateral_description', 'guarantor_name', 'guarantor_phone', 'guarantor_relationship', 'notes']));

        return $this->success($this->formatLoan($loan->fresh()), 'Loan updated.');
    }

    public function destroy(Loan $loan): JsonResponse
    {
        if (! in_array($loan->status, [LoanStatus::Submitted, LoanStatus::Draft, LoanStatus::Denied])) {
            return $this->error('Only draft, submitted, or denied loans can be deleted.', 422);
        }

        $loan->delete();

        return $this->success(null, 'Loan deleted.');
    }

    // ─── Lifecycle Transitions ───────────────────────────────────────────────

    /**
     * Submit draft loan for review.
     * POST /api/v1/loans/{loan}/submit
     */
    public function submit(Request $request, Loan $loan): JsonResponse
    {
        return $this->transition($loan, LoanStatus::Submitted, $request->notes, 'Loan submitted for review.');
    }

    /**
     * Loan officer review — note only (manager approves separately).
     * POST /api/v1/loans/{loan}/review
     */
    public function review(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        if ($loan->status !== LoanStatus::Submitted) {
            return $this->error('Only submitted loans can be reviewed.', 422);
        }

        activity()
            ->performedOn($loan)
            ->causedBy(auth()->user())
            ->withProperties(['note' => $request->notes])
            ->log('reviewed');

        return $this->success($this->formatLoan($loan), 'Loan review recorded.');
    }

    /**
     * Approve a submitted loan.
     * POST /api/v1/loans/{loan}/approve
     */
    public function approve(Request $request, Loan $loan, LoanLifecycleService $lifecycle): JsonResponse
    {
        $request->validate(['notes' => ['nullable', 'string', 'max:1000']]);

        try {
            $loan = $lifecycle->approve($loan, $request->user(), $request->notes);
        } catch (LoanActionException $e) {
            return $this->loanActionError($e);
        }

        return $this->success($this->formatLoan($loan), 'Loan approved.');
    }

    /**
     * Disburse an approved loan — generates repayment schedule.
     * POST /api/v1/loans/{loan}/disburse
     */
    public function disburse(DisburseRequest $request, Loan $loan, LoanLifecycleService $lifecycle): JsonResponse
    {
        try {
            $result = $lifecycle->disburse(
                $loan,
                $request->user(),
                $request->validated(),
                $request->boolean('crb_override'),
                $request->input('crb_override_reason'),
            );
        } catch (LoanActionException $e) {
            return $this->loanActionError($e);
        }

        return $this->success(
            $this->formatLoan($result['loan']->load('schedule')),
            $result['payout_sent']
                ? 'Loan activated — mobile money payout sent to the provider. You\'ll be notified if it fails.'
                : 'Loan disbursed and activated.',
        );
    }

    /** A refused loan action as the API reports it (CRB alerts keep their 409 + summary). */
    private function loanActionError(LoanActionException $e): JsonResponse
    {
        if ($e->context) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), ...$e->context], $e->status);
        }

        return $this->error($e->getMessage(), $e->status);
    }

    /**
     * Settle a payout whose outcome we couldn't confirm (timeout / provider
     * error), after staff have checked the provider's dashboard.
     * POST /api/v1/loans/{loan}/payout/resolve  { outcome: sent|not_sent, notes }
     */
    public function resolvePayout(Request $request, Loan $loan, DisbursementOutcomeService $outcome): JsonResponse
    {
        $data = $request->validate([
            'outcome' => ['required', 'in:sent,not_sent'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $log = $loan->disbursementLogs()->where('status', 'unconfirmed')->latest('id')->first();

        if (! $log) {
            return $this->error('This loan has no unconfirmed payout to resolve.', 422);
        }

        if ($data['outcome'] === 'sent') {
            $outcome->accepted($loan, $log);
            $loan->statusLogs()->create([
                'changed_by' => auth()->id(),
                'from_status' => $loan->status->value,
                'to_status' => $loan->status->value,
                'notes' => "Payout confirmed sent by staff: {$data['notes']}",
            ]);

            return $this->success($this->formatLoan($loan->fresh()), 'Payout confirmed. The borrower has been notified.');
        }

        $outcome->rejected($loan, $log, "Confirmed not sent by staff: {$data['notes']}", auth()->id());

        return $this->success($this->formatLoan($loan->fresh()), 'Disbursement reversed. The loan is back to Approved.');
    }

    /**
     * Deny a submitted or approved loan.
     * POST /api/v1/loans/{loan}/deny
     */
    public function deny(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        if (! in_array($loan->status, [LoanStatus::Submitted, LoanStatus::Approved])) {
            return $this->error('Only submitted or approved loans can be denied.', 422);
        }

        $from = $loan->status;

        $loan->update(['status' => LoanStatus::Denied->value]);

        $this->logStatusChange($loan, $from, LoanStatus::Denied, auth()->id(), $request->reason);

        return $this->success($this->formatLoan($loan->fresh()), 'Loan denied.');
    }

    /**
     * Freeze an active loan.
     * POST /api/v1/loans/{loan}/freeze
     */
    public function freeze(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed])) {
            return $this->error('Only active loans can be frozen.', 422);
        }

        $from = $loan->status;
        $loan->update(['status' => LoanStatus::Frozen->value]);
        $this->logStatusChange($loan, $from, LoanStatus::Frozen, auth()->id(), $request->reason);

        return $this->success($this->formatLoan($loan->fresh()), 'Loan frozen.');
    }

    /**
     * Unfreeze a frozen loan back to active.
     * POST /api/v1/loans/{loan}/unfreeze
     */
    public function unfreeze(Request $request, Loan $loan): JsonResponse
    {
        if ($loan->status !== LoanStatus::Frozen) {
            return $this->error('Only frozen loans can be unfrozen.', 422);
        }

        $loan->update(['status' => LoanStatus::Active->value]);
        $this->logStatusChange($loan, LoanStatus::Frozen, LoanStatus::Active, auth()->id(), $request->notes ?? 'Loan unfrozen.');

        return $this->success($this->formatLoan($loan->fresh()), 'Loan unfrozen.');
    }

    /**
     * Write off a defaulted or frozen loan.
     * POST /api/v1/loans/{loan}/write-off
     */
    public function writeOff(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        if (! auth()->user()?->can('loans.write_off')) {
            return $this->error('Forbidden.', 403);
        }

        if (! in_array($loan->status, [LoanStatus::Frozen, LoanStatus::Defaulted])) {
            return $this->error('Only frozen or defaulted loans can be written off.', 422);
        }

        try {
            $this->approvals->ensureApproved('write_off', $loan->id, $request->user(), (float) $loan->outstanding_balance, 'writing off this loan');
        } catch (LoanActionException $e) {
            return $this->loanActionError($e);
        }

        $result = DB::transaction(function () use ($loan, $request) {
            $loan = Loan::whereKey($loan->id)->lockForUpdate()->firstOrFail();

            // Re-checked under the lock: two clicks must not write it off twice.
            if (! in_array($loan->status, [LoanStatus::Frozen, LoanStatus::Defaulted])) {
                return null;
            }

            $from = $loan->status;
            $loan->update(['status' => LoanStatus::WrittenOff->value]);
            $this->logStatusChange($loan, $from, LoanStatus::WrittenOff, auth()->id(), $request->reason);

            LoanWriteoff::create([
                'loan_id' => $loan->id,
                'written_off_by' => auth()->id(),
                'written_off_amount' => $loan->outstanding_balance,
                'reason' => $request->reason,
            ]);

            app(GlLedgerService::class)->postWriteOff($loan);

            return $loan;
        });

        if (! $result) {
            return $this->error('This loan has already been written off.', 422);
        }

        // Auto-suggest repo listings from collateral (best-effort, Growth+ only)
        try {
            $this->autoListCollateralFromWriteoff($loan);
        } catch (\Throwable) {
        }

        return $this->success($this->formatLoan($loan->fresh()), 'Loan written off.');
    }

    /**
     * Auto-create draft repo marketplace listings for each collateral item
     * when a loan is written off and the tenant has the repo_marketplace feature.
     */
    private function autoListCollateralFromWriteoff(Loan $loan): void
    {
        $tenantId = (string) (tenant('id') ?? 'local');

        // Check plan feature
        $sub = \Illuminate\Support\Facades\DB::table('subscriptions')
            ->where('tenant_id', $tenantId)->where('status', 'active')
            ->orderByDesc('id')->first();
        if (! $sub) {
            return;
        }
        $plan = \Illuminate\Support\Facades\DB::table('plan_configs')->where('plan', $sub->plan)->first();
        if (! $plan) {
            return;
        }
        $features = json_decode($plan->features ?? '{}', true);
        if (empty($features['repo_marketplace'])) {
            return;
        }

        // Resolve tenant name
        $tenantName = \Illuminate\Support\Facades\DB::table('tenants')->where('id', $tenantId)->value('name') ?? $tenantId;

        $collaterals = CollateralItem::where('loan_id', $loan->id)->get();
        foreach ($collaterals as $collateral) {
            // Skip if already listed
            $alreadyListed = RepoItem::where('tenant_id', $tenantId)
                ->where('title', 'like', '%'.$collateral->description.'%')
                ->exists();
            if ($alreadyListed) {
                continue;
            }

            RepoItem::create([
                'tenant_id' => $tenantId,
                'tenant_name' => $tenantName,
                'title' => $collateral->description,
                'description' => "Repossessed collateral from loan {$loan->loan_number}. ".($collateral->notes ?? ''),
                'price' => $collateral->estimated_value ?? 0,
                'original_value' => $collateral->estimated_value,
                'category' => $this->guessCollateralCategory($collateral->type ?? ''),
                'condition' => 'fair',
                'location' => null,
                'is_active' => false, // draft — staff must activate manually
            ]);
        }
    }

    private function guessCollateralCategory(string $type): string
    {
        $map = [
            'vehicle' => 'vehicle',
            'car' => 'vehicle',
            'land' => 'land',
            'property' => 'land',
            'electronics' => 'electronics',
            'equipment' => 'equipment',
            'furniture' => 'furniture',
        ];
        foreach ($map as $keyword => $category) {
            if (stripos($type, $keyword) !== false) {
                return $category;
            }
        }

        return 'other';
    }

    /**
     * Restructure an active loan (change terms).
     * POST /api/v1/loans/{loan}/restructure
     */
    public function restructure(Request $request, Loan $loan, LoanLifecycleService $lifecycle): JsonResponse
    {
        $data = $request->validate([
            'tenure' => ['required', 'integer', 'min:1', 'max:600'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        try {
            $loan = $lifecycle->restructure($loan, $request->user(), (int) $data['tenure'], $data['reason']);
        } catch (LoanActionException $e) {
            return $this->loanActionError($e);
        }

        return $this->success($this->formatLoan($loan->load('schedule')), 'Loan restructured.');
    }

    /**
     * Get full repayment schedule.
     * GET /api/v1/loans/{loan}/schedule
     */
    public function schedule(Loan $loan): JsonResponse
    {
        $loan->load('schedule');

        $rows = $loan->schedule->map(fn ($s) => [
            'id' => $s->id,
            'instalment_number' => $s->instalment_number,
            'due_date' => $s->due_date->toDateString(),
            'principal_due' => (float) $s->principal_due,
            'interest_due' => (float) $s->interest_due,
            'fee_due' => (float) $s->fee_due,
            'total_due' => (float) $s->total_due,
            'principal_paid' => (float) $s->principal_paid,
            'interest_paid' => (float) $s->interest_paid,
            'total_paid' => (float) $s->total_paid,
            'outstanding' => (float) $s->outstanding,
            'is_paid' => $s->is_paid,
            'paid_date' => $s->paid_date?->toDateString(),
            'days_overdue' => $s->days_overdue,
            'penalty_accrued' => (float) $s->penalty_accrued,
        ]);

        return $this->success($rows);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateLoanNumber(): string
    {
        $yearMonth = now()->format('Ym');
        $last = Loan::withTrashed()
            ->where('loan_number', 'like', "LN-{$yearMonth}-%")
            ->max('loan_number');

        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return 'LN-'.$yearMonth.'-'.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function logStatusChange(Loan $loan, ?LoanStatus $from, LoanStatus $to, ?int $userId, ?string $notes): void
    {
        $loan->statusLogs()->create([
            'changed_by' => $userId,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'notes' => $notes,
        ]);
    }

    private function transition(Loan $loan, LoanStatus $to, ?string $notes, string $message): JsonResponse
    {
        if (! $loan->status->canTransitionTo($to)) {
            return $this->error("Cannot transition from {$loan->status->label()} to {$to->label()}.", 422);
        }

        $from = $loan->status;
        $loan->update(['status' => $to->value]);
        $this->logStatusChange($loan, $from, $to, auth()->id(), $notes);

        return $this->success($this->formatLoan($loan->fresh()), $message);
    }

    private function formatLoan(Loan $l, bool $full = false): array
    {
        $data = [
            'id' => $l->id,
            'loan_number' => $l->loan_number,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'status_color' => $l->status->color(),
            'borrower' => $l->relationLoaded('borrower') ? [
                'id' => $l->borrower->id,
                'name' => $l->borrower->full_name,
                'borrower_number' => $l->borrower->borrower_number,
                'phone' => $l->borrower->phone,
            ] : ['id' => $l->borrower_id],
            'loan_type' => $l->relationLoaded('loanType') ? ['id' => $l->loanType->id, 'name' => $l->loanType->name] : null,
            'loan_plan' => $l->relationLoaded('loanPlan') ? ['id' => $l->loanPlan->id, 'name' => $l->loanPlan->name] : null,
            'principal_amount' => (float) $l->principal_amount,
            'interest_amount' => (float) $l->interest_amount,
            'processing_fee' => (float) $l->processing_fee,
            'insurance_fee' => (float) $l->insurance_fee,
            'total_payable' => (float) $l->total_payable,
            'total_paid' => (float) $l->total_paid,
            'outstanding_balance' => (float) $l->outstanding_balance,
            'penalty_balance' => (float) $l->penalty_balance,
            'interest_rate' => (float) $l->interest_rate,
            'interest_type' => $l->interest_type,
            'repayment_schedule' => $l->repayment_schedule,
            'tenure' => $l->tenure,
            'tenure_type' => $l->tenure_type,
            'application_date' => $l->application_date?->toDateString(),
            'approval_date' => $l->approval_date?->toDateString(),
            'disbursement_date' => $l->disbursement_date?->toDateString(),
            'first_repayment_date' => $l->first_repayment_date?->toDateString(),
            'maturity_date' => $l->maturity_date?->toDateString(),
            'created_by' => $l->relationLoaded('createdBy') ? $l->createdBy?->name : null,
        ];

        if ($full) {
            $data['loan_purpose'] = $l->loan_purpose;
            $data['collateral_description'] = $l->collateral_description;
            $data['guarantor_name'] = $l->guarantor_name;
            $data['guarantor_phone'] = $l->guarantor_phone;
            $data['guarantor_relationship'] = $l->guarantor_relationship;
            $data['disbursement_method'] = $l->disbursement_method?->value;
            $data['disbursement_account'] = $l->disbursement_account;
            $data['disbursement_reference'] = $l->disbursement_reference;
            $data['notes'] = $l->notes;
            $data['approved_by'] = $l->relationLoaded('approvedBy') ? $l->approvedBy?->name : null;
            $data['disbursed_by'] = $l->relationLoaded('disbursedBy') ? $l->disbursedBy?->name : null;

            if ($l->relationLoaded('schedule')) {
                $data['schedule'] = $l->schedule->map(fn ($s) => [
                    'id' => $s->id,
                    'instalment_number' => $s->instalment_number,
                    'due_date' => $s->due_date->toDateString(),
                    'total_due' => (float) $s->total_due,
                    'total_paid' => (float) $s->total_paid,
                    'outstanding' => (float) $s->outstanding,
                    'is_paid' => $s->is_paid,
                    'days_overdue' => $s->days_overdue,
                ])->values();
            }

            if ($l->relationLoaded('statusLogs')) {
                $data['status_logs'] = $l->statusLogs->map(fn ($log) => [
                    'from_status' => $log->from_status,
                    'from_label' => $log->from_status ? ucwords(str_replace('_', ' ', $log->from_status)) : null,
                    'to_status' => $log->to_status,
                    'to_label' => $log->to_status ? ucwords(str_replace('_', ' ', $log->to_status)) : null,
                    'notes' => $log->notes,
                    'changed_by' => $log->changedBy?->name,
                    'created_at' => $log->created_at->format('d M Y H:i'),
                ])->values();
            }
        }

        return $data;
    }
}
