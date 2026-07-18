<?php

namespace App\Services;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanInterestAccrual;
use App\Traits\UsesBcMath;
use Carbon\Carbon;

class InterestAccrualService
{
    use UsesBcMath;

    /**
     * Accrue interest for all active loans on the given date.
     * Returns a summary array.
     */
    public function accrueForDate(Carbon $date, bool $dryRun = false): array
    {
        $loans = Loan::whereIn('status', ['disbursed', 'active'])
            ->where('outstanding_balance', '>', 0)
            ->get();

        $totalAccrued = '0';
        $loansProcessed = 0;
        $loansSuspended = 0;
        $skipped = 0;

        foreach ($loans as $loan) {
            // Skip if already accrued for this date
            $exists = LoanInterestAccrual::where('loan_id', $loan->id)
                ->whereDate('accrual_date', $date->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $accrual = $this->accrueForLoan($loan, $date, $dryRun);

            if ($accrual['is_suspended']) {
                $loansSuspended++;
            } else {
                $totalAccrued = bcadd($totalAccrued, (string) $accrual['accrued_amount'], 2);
            }
            $loansProcessed++;
        }

        return [
            'accrual_date' => $date->toDateString(),
            'loans_processed' => $loansProcessed,
            'loans_suspended' => $loansSuspended,
            'skipped' => $skipped,
            'total_accrued' => (float) $totalAccrued,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Accrue interest for a single loan on the given date.
     */
    public function accrueForLoan(Loan $loan, Carbon $date, bool $dryRun = false): array
    {
        $outstanding = (float) $loan->outstanding_balance;
        $annualRate = (float) $loan->interest_rate;      // stored as % e.g. 24.00
        $dailyRateStr = bcdiv(bcdiv((string) $annualRate, '365', 10), '100', 10); // decimal fraction
        $dailyRate = (float) $this->bcround($dailyRateStr, 6);
        $accrued = (float) $this->bcround(bcmul((string) $outstanding, $dailyRateStr, 10));

        // Stage 3 (non-performing) → suspend accrual
        $isSuspended = $this->isNonPerforming($loan);

        if (! $dryRun) {
            LoanInterestAccrual::create([
                'loan_id' => $loan->id,
                'accrual_date' => $date->toDateString(),
                'principal_outstanding' => $outstanding,
                'daily_rate' => $dailyRate,
                'accrued_amount' => $isSuspended ? 0.00 : $accrued,
                'status' => 'posted',
                'is_suspended' => $isSuspended,
            ]);
        }

        return [
            'loan_id' => $loan->id,
            'accrued_amount' => $isSuspended ? 0.00 : $accrued,
            'is_suspended' => $isSuspended,
            'daily_rate' => $dailyRate,
        ];
    }

    /**
     * Monthly accrual summary (total accrued per month).
     */
    public function monthlySummary(int $year): array
    {
        $rows = LoanInterestAccrual::where('status', 'posted')
            ->where('is_suspended', false)
            ->whereYear('accrual_date', $year)
            ->get()
            ->groupBy(fn ($r) => \Carbon\Carbon::parse($r->accrual_date)->format('Y-m'))
            ->map(fn ($g) => [
                'month' => $g->first()->accrual_date->format('Y-m'),
                'total_accrued' => (float) $g->reduce(
                    fn ($carry, $r) => bcadd($carry, (string) $r->accrued_amount, 2),
                    '0'
                ),
                'loans' => $g->pluck('loan_id')->unique()->count(),
            ])
            ->values();

        return $rows->toArray();
    }

    /**
     * A loan is non-performing if its oldest overdue installment is 90+ DPD.
     */
    private function isNonPerforming(Loan $loan): bool
    {
        $oldest = \App\Models\Tenant\LoanSchedule::where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->value('due_date');

        if (! $oldest) {
            return false;
        }

        $dpd = (int) \Carbon\Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay());

        return $dpd >= 90;
    }
}
