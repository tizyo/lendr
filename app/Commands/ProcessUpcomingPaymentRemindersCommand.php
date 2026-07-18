<?php

namespace App\Commands;

use App\Jobs\SendLoanEventNotificationJob;
use App\Models\Tenant\LoanSchedule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs daily to send SMS payment reminders to borrowers whose next
 * installment is due in exactly 1, 3, or 7 days.
 *
 * Register: Schedule::command(ProcessUpcomingPaymentRemindersCommand::class)->dailyAt('08:00');
 */
class ProcessUpcomingPaymentRemindersCommand extends Command
{
    protected $signature = 'lendr:payment-reminders {--dry-run : Preview without sending SMS}';

    protected $description = 'Send SMS reminders for installments due in 1, 3, or 7 days';

    private const REMINDER_DAYS = [7, 3, 1];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $sent = 0;

        foreach (self::REMINDER_DAYS as $daysAhead) {
            $targetDate = now()->addDays($daysAhead)->toDateString();

            LoanSchedule::with(['loan:id,loan_number,borrower_id', 'loan.borrower:id,phone,first_name'])
                ->where('is_paid', false)
                ->whereDate('due_date', $targetDate)
                ->whereHas('loan', fn ($q) => $q->whereIn('status', ['disbursed', 'active']))
                ->chunkById(100, function ($installments) use ($daysAhead, $dryRun, &$sent) {
                    foreach ($installments as $installment) {
                        $loan = $installment->loan;
                        if (! $loan || ! $loan->borrower?->phone) {
                            continue;
                        }

                        if (! $dryRun) {
                            dispatch(new SendLoanEventNotificationJob($loan->id, 'upcoming_payment', [
                                'due_date' => $installment->due_date->toDateString(),
                                'amount_due' => number_format((float) $installment->outstanding, 2),
                            ]));
                        }

                        $this->line("  Reminder ({$daysAhead}d): Loan #{$loan->loan_number} — {$loan->borrower->phone}");
                        $sent++;
                    }
                });
        }

        $this->info($dryRun ? "[DRY RUN] Would send {$sent} reminders." : "Sent {$sent} payment reminders.");
        Log::info('[PaymentReminders] Complete', compact('sent', 'dryRun'));

        return self::SUCCESS;
    }
}
