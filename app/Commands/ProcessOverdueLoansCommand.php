<?php

namespace App\Commands;

use App\Enums\LoanStatus;
use App\Jobs\RecalculateCreditScoreJob;
use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Services\NotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Runs daily to:
 *  1. Update days_overdue + penalty_accrued on overdue schedule installments
 *  2. Accrue daily penalty to loan.penalty_balance
 *  3. Auto-default loans overdue beyond (grace_period_days + 90) days
 *  4. Dispatch credit score recalculation + borrower SMS notification
 *
 * Register: Schedule::command(ProcessOverdueLoansCommand::class)->dailyAt('01:00');
 */
class ProcessOverdueLoansCommand extends Command
{
    protected $signature   = 'lendr:process-overdue {--dry-run : Preview without persisting changes}';
    protected $description = 'Accrue penalties on overdue loans and auto-default severely delinquent loans';

    private const AUTO_DEFAULT_DAYS = 90; // overdue days after grace period before auto-default

    public function handle(): int
    {
        $dryRun  = $this->option('dry-run');
        $today   = now()->toDateString();
        $updated = 0;
        $defaulted = 0;

        $this->info($dryRun ? '[DRY RUN] Processing overdue loans…' : 'Processing overdue loans…');

        // Load active loans that have at least one overdue, unpaid installment
        Loan::with(['borrower:id,phone,first_name', 'schedule' => fn ($q) => $q->where('is_paid', false)->where('due_date', '<', $today)])
            ->whereIn('status', [LoanStatus::Disbursed->value, LoanStatus::Active->value])
            ->whereHas('schedule', fn ($q) => $q->where('is_paid', false)->where('due_date', '<', $today))
            ->chunkById(100, function ($loans) use ($dryRun, $today, &$updated, &$defaulted) {
                foreach ($loans as $loan) {
                    $this->processLoan($loan, $today, $dryRun, $updated, $defaulted);
                }
            });

        $this->info("Done. Updated: {$updated} loans, Auto-defaulted: {$defaulted} loans.");
        Log::info('[ProcessOverdueLoans] Complete', compact('updated', 'defaulted', 'dryRun'));

        return self::SUCCESS;
    }

    private function processLoan(Loan $loan, string $today, bool $dryRun, int &$updated, int &$defaulted): void
    {
        $overdueInstallments = $loan->schedule->filter(fn (LoanSchedule $s) => ! $s->is_paid && $s->due_date->lt($today));

        if ($overdueInstallments->isEmpty()) {
            return;
        }

        $penaltyRate        = (float) ($loan->penalty_rate ?? 0);    // % per day or per period
        $gracePeriodDays    = (int)   ($loan->grace_period_days ?? 0);
        $maxOverdueDays     = 0;
        $newPenaltyAccrued  = 0.0;

        DB::transaction(function () use ($loan, $overdueInstallments, $penaltyRate, $gracePeriodDays, $today, $dryRun, &$maxOverdueDays, &$newPenaltyAccrued) {
            foreach ($overdueInstallments as $installment) {
                $daysOverdue = (int) now()->diffInDays($installment->due_date);
                $effectiveDays = max(0, $daysOverdue - $gracePeriodDays);

                // Daily penalty on outstanding balance of this installment
                $dailyPenalty = $effectiveDays > 0
                    ? round((float) $installment->outstanding * ($penaltyRate / 100 / 30), 4) // monthly rate / 30
                    : 0;

                $maxOverdueDays = max($maxOverdueDays, $daysOverdue);
                $newPenaltyAccrued += $dailyPenalty;

                if (! $dryRun) {
                    $installment->update([
                        'days_overdue'    => $daysOverdue,
                        'penalty_accrued' => $dailyPenalty,
                    ]);
                }
            }

            if (! $dryRun && $newPenaltyAccrued > 0) {
                $loan->increment('penalty_balance', $newPenaltyAccrued);
            }
        });

        $updated++;

        // Auto-default if overdue beyond threshold (after grace period)
        $effectiveOverdueDays = max(0, $maxOverdueDays - $gracePeriodDays);

        if ($effectiveOverdueDays >= self::AUTO_DEFAULT_DAYS && $loan->status->canTransitionTo(LoanStatus::Defaulted)) {
            if (! $dryRun) {
                $loan->update(['status' => LoanStatus::Defaulted->value]);

                dispatch(new SendLoanEventNotificationJob($loan->id, 'defaulted'));
                dispatch(new RecalculateCreditScoreJob($loan->borrower_id));

                app(NotificationService::class)->notifyRoles(
                    ['branch_manager', 'super_admin'],
                    'loan_defaulted',
                    "Loan {$loan->loan_number} auto-defaulted",
                    "{$effectiveOverdueDays} days overdue (beyond grace period). Outstanding: ZMW ".number_format((float) $loan->outstanding_balance, 2),
                    ['loan_id' => $loan->id, 'loan_number' => $loan->loan_number],
                );
            }

            $this->warn("  Auto-defaulted Loan #{$loan->loan_number} (overdue {$effectiveOverdueDays}d)");
            $defaulted++;
        } elseif ($newPenaltyAccrued > 0) {
            if (! $dryRun) {
                dispatch(new SendLoanEventNotificationJob($loan->id, 'overdue'));
            }

            $this->line("  Penalised Loan #{$loan->loan_number}: +{$newPenaltyAccrued} ZMW penalty ({$maxOverdueDays}d overdue)");
        }
    }
}
