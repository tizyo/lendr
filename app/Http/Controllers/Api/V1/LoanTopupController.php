<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanTopup;
use App\Services\LoanCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanTopupController extends BaseApiController
{
    public function __construct(private LoanCalculatorService $calculator) {}

    /**
     * List all top-up requests for a loan.
     * GET /api/v1/loans/{loan}/topups
     */
    public function index(Loan $loan): JsonResponse
    {
        $topups = $loan->topups()
            ->with(['requestedBy:id,name', 'approvedBy:id,name'])
            ->get();

        return $this->success($topups->map(fn ($t) => $this->format($t)));
    }

    /**
     * Submit a new top-up request.
     * POST /api/v1/loans/{loan}/topups
     */
    public function store(Request $request, Loan $loan): JsonResponse
    {
        if (! in_array($loan->status, [LoanStatus::Active, LoanStatus::Disbursed])) {
            return $this->error('Only active or disbursed loans can receive top-ups.', 422);
        }

        if ($loan->topups()->where('status', 'pending')->exists()) {
            return $this->error('A pending top-up request already exists for this loan.', 422);
        }

        $data = $request->validate([
            'topup_amount' => ['required', 'numeric', 'min:1'],
            'new_tenure'   => ['nullable', 'integer', 'min:1'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ]);

        $topup = LoanTopup::create([
            ...$data,
            'loan_id'      => $loan->id,
            'requested_by' => auth()->id(),
            'status'       => 'pending',
        ]);

        return $this->success(
            $this->format($topup->load('requestedBy:id,name')),
            'Top-up request submitted.',
            201
        );
    }

    /**
     * Approve a pending top-up: increases principal and regenerates unpaid schedule.
     * POST /api/v1/loans/{loan}/topups/{topup}/approve
     */
    public function approve(Request $request, Loan $loan, LoanTopup $topup): JsonResponse
    {
        if ($topup->loan_id !== $loan->id) {
            abort(404);
        }

        if ($topup->status !== 'pending') {
            return $this->error('Only pending top-ups can be approved.', 422);
        }

        DB::transaction(function () use ($loan, $topup) {
            $addAmount   = (float) $topup->topup_amount;
            $outstanding = (float) $loan->outstanding_balance + $addAmount;
            $newTenure   = $topup->new_tenure ?? (int) $loan->tenure;
            $plan        = $loan->loanPlan;

            $amounts = $this->calculator->calculateAmounts($plan, $outstanding, $newTenure);

            $loan->update([
                'principal_amount'    => bcadd((string) $loan->principal_amount, (string) $addAmount, 2),
                'tenure'              => $newTenure,
                'interest_amount'     => $amounts['interest_amount'],
                'total_payable'       => bcadd((string) $loan->total_paid, (string) bcadd((string) $outstanding, (string) $amounts['interest_amount'], 2), 2),
                'outstanding_balance' => bcadd((string) $outstanding, (string) $amounts['interest_amount'], 2),
            ]);

            // Delete remaining unpaid instalments and regenerate
            $loan->schedule()->where('is_paid', false)->delete();

            $scheduleRows   = $this->calculator->generateSchedule(
                $plan,
                $outstanding,
                $newTenure,
                now()->toDateString(),
                $amounts['interest_amount']
            );

            $lastInstalment = $loan->schedule()->where('is_paid', true)->max('instalment_number') ?? 0;

            foreach ($scheduleRows as $i => $row) {
                LoanSchedule::create([
                    'loan_id'           => $loan->id,
                    'instalment_number' => $lastInstalment + $i + 1,
                    'due_date'          => $row['due_date'],
                    'principal_due'     => $row['principal_due'],
                    'interest_due'      => $row['interest_due'],
                    'fee_due'           => $row['fee_due'],
                    'total_due'         => $row['total_due'],
                    'outstanding'       => $row['outstanding'],
                ]);
            }

            $topup->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            activity()
                ->performedOn($loan)
                ->causedBy(auth()->user())
                ->withProperties([
                    'topup_amount' => (float) $topup->topup_amount,
                    'new_tenure'   => $newTenure,
                ])
                ->log('topup_approved');
        });

        return $this->success(
            $this->format($topup->fresh()->load(['requestedBy:id,name', 'approvedBy:id,name'])),
            'Top-up approved.'
        );
    }

    /**
     * Reject a pending top-up.
     * POST /api/v1/loans/{loan}/topups/{topup}/reject
     */
    public function reject(Request $request, Loan $loan, LoanTopup $topup): JsonResponse
    {
        if ($topup->loan_id !== $loan->id) {
            abort(404);
        }

        if ($topup->status !== 'pending') {
            return $this->error('Only pending top-ups can be rejected.', 422);
        }

        $data = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $topup->update([
            'status'           => 'rejected',
            'rejection_reason' => $data['reason'],
            'approved_by'      => auth()->id(),
            'approved_at'      => now(),
        ]);

        activity()
            ->performedOn($loan)
            ->causedBy(auth()->user())
            ->withProperties(['reason' => $data['reason']])
            ->log('topup_rejected');

        return $this->success(
            $this->format($topup->fresh()->load(['requestedBy:id,name', 'approvedBy:id,name'])),
            'Top-up rejected.'
        );
    }

    private function format(LoanTopup $t): array
    {
        return [
            'id'               => $t->id,
            'topup_amount'     => (float) $t->topup_amount,
            'new_tenure'       => $t->new_tenure,
            'status'           => $t->status,
            'notes'            => $t->notes,
            'rejection_reason' => $t->rejection_reason,
            'requested_by'     => $t->requestedBy?->name,
            'approved_by'      => $t->approvedBy?->name,
            'approved_at'      => $t->approved_at?->format('d M Y'),
            'created_at'       => $t->created_at->format('d M Y'),
        ];
    }
}
