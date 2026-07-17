<?php

namespace App\Commands;

use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Services\Mail\TenantMailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Runs on the 1st of each month to send loan account statements to borrowers.
 * Covers all borrowers with at least one active/disbursed loan that has an email.
 *
 * Register: Schedule::command(SendBorrowerStatementsCommand::class)->monthlyOn(1, '06:00');
 */
class SendBorrowerStatementsCommand extends Command
{
    protected $signature   = 'lendr:send-statements {--month= : Month (1-12, defaults to previous month)} {--year= : Year (defaults to current year)} {--dry-run : Preview without sending}';
    protected $description = 'Email monthly loan account statements to borrowers with active loans';

    public function handle(TenantMailService $mailer): int
    {
        $dryRun = $this->option('dry-run');

        // Default to previous calendar month
        $refDate = now()->subMonthNoOverflow()->startOfMonth();
        $month   = (int) ($this->option('month') ?? $refDate->month);
        $year    = (int) ($this->option('year')  ?? $refDate->year);
        $period  = \Carbon\Carbon::createFromDate($year, $month, 1);

        $this->info(sprintf(
            '%sSending statements for %s…',
            $dryRun ? '[DRY RUN] ' : '',
            $period->format('F Y')
        ));

        $sent   = 0;
        $skipped = 0;

        // Borrowers with at least one active loan and a registered email
        Borrower::whereNotNull('email')
            ->whereHas('loans', fn ($q) => $q->whereIn('status', [
                LoanStatus::Disbursed->value,
                LoanStatus::Active->value,
            ]))
            ->with(['loans' => fn ($q) => $q
                ->whereIn('status', [LoanStatus::Disbursed->value, LoanStatus::Active->value])
                ->with(['schedule' => fn ($sq) => $sq->where('is_paid', false)->orderBy('due_date')])
                ->with(['payments'  => fn ($pq) => $pq
                    ->whereYear('payment_date', $year)
                    ->whereMonth('payment_date', $month)
                    ->orderBy('payment_date')
                ]),
            ])
            ->chunkById(50, function ($borrowers) use ($mailer, $period, $dryRun, &$sent, &$skipped) {
                foreach ($borrowers as $borrower) {
                    if ($borrower->loans->isEmpty()) {
                        $skipped++;
                        continue;
                    }

                    if (! $dryRun) {
                        try {
                            $body = $this->buildStatementBody($borrower, $period);
                            $mailer->raw(
                                $borrower->email,
                                "Your LENDR Loan Statement – {$period->format('F Y')}",
                                $body
                            );
                        } catch (\Throwable $e) {
                            $this->warn("  Failed to send to {$borrower->email}: {$e->getMessage()}");
                            Log::warning('[SendStatements] Mail failed', [
                                'borrower_id' => $borrower->id,
                                'error'       => $e->getMessage(),
                            ]);
                            $skipped++;
                            continue;
                        }
                    }

                    $this->line("  Sent to {$borrower->email} ({$borrower->loans->count()} loan(s))");
                    $sent++;
                }
            });

        $this->info("Done. Sent: {$sent}, Skipped: {$skipped}.");
        Log::info('[SendStatements] Complete', compact('sent', 'skipped', 'month', 'year', 'dryRun'));

        return self::SUCCESS;
    }

    private function buildStatementBody(Borrower $borrower, \Carbon\Carbon $period): string
    {
        $name    = trim("{$borrower->first_name} {$borrower->last_name}");
        $lines   = [];
        $lines[] = "Dear {$name},";
        $lines[] = '';
        $lines[] = "Please find below your loan account statement for {$period->format('F Y')}.";
        $lines[] = '';

        foreach ($borrower->loans as $loan) {
            $lines[] = "─────────────────────────────────────";
            $lines[] = "Loan: {$loan->loan_number}";
            $lines[] = "Outstanding Balance: ".number_format((float) $loan->outstanding_balance, 2);

            // Payments made this month
            if ($loan->payments->isNotEmpty()) {
                $lines[] = '';
                $lines[] = "Payments received in {$period->format('F Y')}:";
                foreach ($loan->payments as $payment) {
                    $lines[] = "  {$payment->payment_date->toDateString()}  ZMW ".number_format((float) $payment->amount, 2)."  ({$payment->payment_method->value})";
                }
                $totalPaid = $loan->payments->sum('amount');
                $lines[] = "  Total paid: ZMW ".number_format((float) $totalPaid, 2);
            } else {
                $lines[] = "No payments recorded in {$period->format('F Y')}.";
            }

            // Next upcoming installment
            $nextInstalment = $loan->schedule->first();
            if ($nextInstalment) {
                $lines[] = '';
                $lines[] = "Next instalment due: {$nextInstalment->due_date->toDateString()}  ZMW ".number_format((float) $nextInstalment->total_due, 2);
            }

            $lines[] = '';
        }

        $lines[] = "─────────────────────────────────────";
        $lines[] = "If you have any questions, please contact your loan officer.";
        $lines[] = '';
        $lines[] = "Thank you for banking with us.";
        $lines[] = 'LENDR Team';

        return implode("\n", $lines);
    }
}
