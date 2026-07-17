<?php

namespace App\Services;

use App\Models\Tenant\Branch;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BranchPerformanceService
{
    /**
     * Branch P&L for a given month (YYYY-MM) or 'all'.
     *
     * @return array{
     *   period: string,
     *   disbursements_count: int,
     *   disbursements_amount: float,
     *   repayments_amount: float,
     *   interest_income: float,
     *   fee_income: float,
     *   penalty_income: float,
     *   writeoffs_amount: float,
     *   net_income: float,
     * }
     */
    public function pnl(Branch $branch, string $period = 'all'): array
    {
        $loansQ    = Loan::where('branch_id', $branch->id);
        $paymentsQ = Payment::whereIn('loan_id', Loan::where('branch_id', $branch->id)->select('id'));

        if ($period !== 'all') {
            $from = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
            $to   = $from->copy()->endOfMonth();

            $loansQ->whereBetween('disbursement_date', [$from->toDateString(), $to->toDateString()]);
            $paymentsQ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()]);
        }

        $loans    = $loansQ->get(['id', 'principal_amount', 'interest_amount', 'processing_fee', 'insurance_fee', 'status']);
        $payments = $paymentsQ->get(['interest_allocated', 'penalty_allocated', 'principal_allocated']);

        $disbCount  = $loans->count();
        $disbAmount = $loans->sum('principal_amount');
        $feeIncome  = $loans->sum('processing_fee') + $loans->sum('insurance_fee');

        $interestIncome = $payments->sum('interest_allocated');
        $penaltyIncome  = $payments->sum('penalty_allocated');
        $repaymentsAmt  = $payments->sum(fn ($p) => (float)$p->principal_allocated + (float)$p->interest_allocated);

        $writeoffsAmount = $loans->filter(fn ($l) => (is_object($l->status) ? $l->status->value : $l->status) === 'written_off')
            ->sum('principal_amount');

        return [
            'period'               => $period,
            'disbursements_count'  => $disbCount,
            'disbursements_amount' => (float) $disbAmount,
            'repayments_amount'    => round($repaymentsAmt, 2),
            'interest_income'      => round((float) $interestIncome, 2),
            'fee_income'           => round((float) $feeIncome, 2),
            'penalty_income'       => round((float) $penaltyIncome, 2),
            'writeoffs_amount'     => round((float) $writeoffsAmount, 2),
            'net_income'           => round((float) $interestIncome + (float) $feeIncome + (float) $penaltyIncome, 2),
        ];
    }

    /**
     * Portfolio health snapshot for a branch.
     *
     * @return array{
     *   total_active_loans: int,
     *   total_outstanding: float,
     *   par_30: float,
     *   par_60: float,
     *   par_90: float,
     *   par_90_plus: float,
     *   avg_loan_size: float,
     *   npl_count: int,
     *   npl_rate: float,
     * }
     */
    public function portfolioHealth(Branch $branch): array
    {
        $loanIds = Loan::where('branch_id', $branch->id)
            ->whereIn('status', ['disbursed', 'active', 'defaulted'])
            ->pluck('id');

        $totalActive      = $loanIds->count();
        $totalOutstanding = Loan::whereIn('id', $loanIds)->sum('outstanding_balance');

        $today = now()->toDateString();

        // PAR buckets — loans with overdue instalments
        $par = function (int $daysFrom, ?int $daysTo = null) use ($loanIds, $today): float {
            $overdueLoanIds = LoanSchedule::whereIn('loan_id', $loanIds)
                ->where('is_paid', false)
                ->whereDate('due_date', '<=', now()->subDays($daysFrom)->toDateString())
                ->when($daysTo, fn ($q) => $q->whereDate('due_date', '>', now()->subDays($daysTo)->toDateString()))
                ->distinct()
                ->pluck('loan_id');

            return (float) Loan::whereIn('id', $overdueLoanIds)->sum('outstanding_balance');
        };

        $par30    = $par(30, 60);
        $par60    = $par(60, 90);
        $par90    = $par(90, null);
        $nplCount = Loan::whereIn('id', $loanIds)->where('status', 'defaulted')->count();

        $avgLoanSize = $totalActive > 0 ? round((float) $totalOutstanding / $totalActive, 2) : 0.0;
        $nplRate     = $totalOutstanding > 0 ? round($nplCount / max(1, $totalActive) * 100, 2) : 0.0;

        return [
            'total_active_loans' => $totalActive,
            'total_outstanding'  => round((float) $totalOutstanding, 2),
            'par_30'             => $par30,
            'par_60'             => $par60,
            'par_90'             => $par90,
            'par_90_plus'        => $par90,  // alias
            'avg_loan_size'      => $avgLoanSize,
            'npl_count'          => $nplCount,
            'npl_rate'           => $nplRate,
        ];
    }

    /**
     * Officer league table for a branch — ranked by disbursements.
     *
     * @return array<int, array{
     *   user_id: int,
     *   name: string,
     *   disbursements_count: int,
     *   disbursements_amount: float,
     *   active_loans: int,
     *   collections_amount: float,
     *   avg_loan_size: float,
     * }>
     */
    public function officerLeague(Branch $branch, ?string $period = null): array
    {
        $staffIds = User::where('branch', $branch->code)->pluck('id');

        $results = [];

        foreach ($staffIds as $userId) {
            $query = Loan::where('created_by', $userId)
                ->where('branch_id', $branch->id);

            if ($period) {
                $from = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
                $to   = $from->copy()->endOfMonth();
                $query->whereBetween('disbursement_date', [$from->toDateString(), $to->toDateString()]);
            }

            $loans = $query->get(['id', 'principal_amount', 'status', 'outstanding_balance']);

            if ($loans->isEmpty()) continue;

            $loanIds          = $loans->pluck('id');
            $disbCount        = $loans->count();
            $disbAmount       = (float) $loans->sum('principal_amount');
            $activeLoans      = $loans->filter(fn ($l) => in_array(
                is_object($l->status) ? $l->status->value : $l->status,
                ['disbursed', 'active']
            ))->count();

            $collectionsAmt = (float) Payment::whereIn('loan_id', $loanIds)->sum(
                DB::raw('principal_allocated + interest_allocated')
            );

            $user = User::find($userId);

            $results[] = [
                'user_id'             => $userId,
                'name'                => $user?->name ?? 'Unknown',
                'disbursements_count' => $disbCount,
                'disbursements_amount'=> round($disbAmount, 2),
                'active_loans'        => $activeLoans,
                'collections_amount'  => round($collectionsAmt, 2),
                'avg_loan_size'       => $disbCount > 0 ? round($disbAmount / $disbCount, 2) : 0.0,
            ];
        }

        usort($results, fn ($a, $b) => $b['disbursements_amount'] <=> $a['disbursements_amount']);

        return $results;
    }
}
