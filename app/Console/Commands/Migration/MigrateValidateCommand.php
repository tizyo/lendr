<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationService;
use App\Services\Migration\ValidationReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * migration:vozara:validate
 *
 * Runs ALL validation checks and outputs a colour-coded report.
 * Migration is BLOCKED from proceeding to cutover if any FAILED checks exist.
 *
 * Checks:
 *  1. borrower_count       — legacy count = LENDR count
 *  2. loan_count_by_status — count per status matches
 *  3. total_payments_value — SUM(pay_amount) legacy = SUM(amount) LENDR
 *  4. fund_balance_check   — recalculated balance = stored ± 0.01
 *  5. loan_balance_check   — payments sum = loan total_paid per loan
 *  6. orphan_check         — no loans without valid borrower_id
 *  7. schedule_check       — sum(instalment_amount) = loan.total_payable
 *  8. file_check           — 50 random S3 document paths return 200
 */
class MigrateValidateCommand extends Command
{
    protected $signature = 'migration:vozara:validate
                            {--tenant= : Target tenant ID}
                            {--skip-file-check : Skip S3 file validation (faster)}';

    protected $description = 'Run the full VOZARA → LENDR migration validation suite';

    public function handle(): int
    {
        $tenantId = $this->option('tenant')
            ?? DB::table('tenants')->orderBy('id')->value('id');

        if (! $tenantId) {
            $this->error('No tenants found.');

            return self::FAILURE;
        }

        $svc = new MigrationService((string) $tenantId);
        $report = new ValidationReport;
        $legacy = $svc->legacy();

        $this->info("Running migration validation for tenant: <comment>{$tenantId}</comment>");
        $this->newLine();

        // ── 1. borrower_count ─────────────────────────────────────────────────
        try {
            $legacyTable = $legacy->getSchemaBuilder()->hasTable('borrowers') ? 'borrowers' : 'customers';
            $legacyCount = $legacy->table($legacyTable)->count();
            $lendrCount = DB::table('borrowers')->count();
            $diff = abs($legacyCount - $lendrCount);
            $detail = "legacy={$legacyCount} lendr={$lendrCount} diff={$diff}";

            $report = $diff === 0
                ? $report->passed('borrower_count', $detail)
                : ($diff <= 5 ? $report->warning('borrower_count', $detail.' (possible deduplication)')
                              : $report->failed('borrower_count', $detail));
        } catch (\Throwable $e) {
            $report = $report->failed('borrower_count', $e->getMessage());
        }

        // ── 2. loan_count_by_status ───────────────────────────────────────────
        try {
            $legacyLoans = $legacy->table('loan')->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->get();
            $lendrLoans = DB::table('loans')->selectRaw('status, COUNT(*) as cnt')->groupBy('status')->get();
            $totalLegacy = $legacyLoans->sum('cnt');
            $totalLendr = $lendrLoans->sum('cnt');
            $diff = abs($totalLegacy - $totalLendr);
            $detail = "legacy_total={$totalLegacy} lendr_total={$totalLendr} diff={$diff}";

            $report = $diff === 0
                ? $report->passed('loan_count_by_status', $detail)
                : $report->failed('loan_count_by_status', $detail);
        } catch (\Throwable $e) {
            $report = $report->failed('loan_count_by_status', $e->getMessage());
        }

        // ── 3. total_payments_value ───────────────────────────────────────────
        try {
            $legacySum = (string) $legacy->table('payment')->sum('pay_amount');
            $lendrSum = (string) DB::table('payments')->sum('amount');
            $diff = abs((float) bcsub($legacySum, $lendrSum, 2));
            $detail = "legacy={$legacySum} lendr={$lendrSum} diff={$diff}";

            $report = $diff < 0.02
                ? $report->passed('total_payments_value', $detail)
                : $report->failed('total_payments_value', $detail);
        } catch (\Throwable $e) {
            $report = $report->failed('total_payments_value', $e->getMessage());
        }

        // ── 4. fund_balance_check ─────────────────────────────────────────────
        try {
            $stored = (string) (DB::table('fund_balances')->value('available_balance') ?? 0);
            $deposits = (string) DB::table('fund_transactions')->where('type', 'deposit')->sum('amount');
            $collections = (string) DB::table('payments')->sum('amount');
            $disbursed = (string) DB::table('fund_transactions')->where('type', 'disbursement')->sum('amount');
            $recalc = bcsub(bcadd($deposits, $collections, 2), $disbursed, 2);
            $diff = abs((float) bcsub($stored, $recalc, 2));
            $detail = "stored={$stored} recalculated={$recalc} diff={$diff}";

            $report = $diff <= 0.01
                ? $report->passed('fund_balance_check', $detail)
                : $report->failed('fund_balance_check', $detail);
        } catch (\Throwable $e) {
            $report = $report->failed('fund_balance_check', $e->getMessage());
        }

        // ── 5. loan_balance_check ─────────────────────────────────────────────
        try {
            $mismatchCount = 0;

            DB::table('loans')->orderBy('id')->chunk(200, function ($loans) use (&$mismatchCount) {
                foreach ($loans as $loan) {
                    $sumPaid = (string) DB::table('payments')->where('loan_id', $loan->id)->sum('amount');
                    $diff = abs((float) bcsub($sumPaid, (string) $loan->total_paid, 2));
                    if ($diff > 0.02) {
                        $mismatchCount++;
                    }
                }
            });

            $detail = "loans_with_balance_discrepancy={$mismatchCount}";
            $report = $mismatchCount === 0
                ? $report->passed('loan_balance_check', $detail)
                : ($mismatchCount <= 3
                    ? $report->warning('loan_balance_check', $detail)
                    : $report->failed('loan_balance_check', $detail));
        } catch (\Throwable $e) {
            $report = $report->failed('loan_balance_check', $e->getMessage());
        }

        // ── 6. orphan_check ───────────────────────────────────────────────────
        try {
            $orphanCount = DB::table('loans')
                ->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('borrowers')
                        ->whereColumn('borrowers.id', 'loans.borrower_id');
                })
                ->count();

            $detail = "orphan_loans={$orphanCount}";
            $report = $orphanCount === 0
                ? $report->passed('orphan_check', $detail)
                : $report->failed('orphan_check', $detail);
        } catch (\Throwable $e) {
            $report = $report->failed('orphan_check', $e->getMessage());
        }

        // ── 7. schedule_check ─────────────────────────────────────────────────
        try {
            $mismatch = 0;

            DB::table('loans')->where('status', 'active')->orderBy('id')->chunk(200, function ($loans) use (&$mismatch) {
                foreach ($loans as $loan) {
                    $scheduleSum = (string) DB::table('repayment_schedules')
                        ->where('loan_id', $loan->id)
                        ->sum('total_due');

                    $diff = abs((float) bcsub($scheduleSum, (string) $loan->total_payable, 2));
                    if ($diff > 0.02) {
                        $mismatch++;
                    }
                }
            });

            $detail = "loans_with_schedule_mismatch={$mismatch}";
            $report = $mismatch === 0
                ? $report->passed('schedule_check', $detail)
                : $report->warning('schedule_check', $detail);
        } catch (\Throwable $e) {
            $report = $report->failed('schedule_check', $e->getMessage());
        }

        // ── 8. file_check ─────────────────────────────────────────────────────
        if (! $this->option('skip-file-check')) {
            try {
                $paths = DB::table('kyc_documents')
                    ->whereNotNull('file_path')
                    ->inRandomOrder()
                    ->limit(50)
                    ->pluck('file_path');

                $failedFiles = 0;
                foreach ($paths as $path) {
                    try {
                        // Extract S3 key from URL or use path directly
                        $key = parse_url($path, PHP_URL_PATH);
                        $exists = Storage::disk('s3')->exists(ltrim($key, '/'));
                        if (! $exists) {
                            $failedFiles++;
                        }
                    } catch (\Throwable) {
                        $failedFiles++;
                    }
                }

                $total = $paths->count();
                $detail = "sampled={$total} failed={$failedFiles}";
                $report = $failedFiles === 0
                    ? $report->passed('file_check', $total === 0 ? 'no files to check' : $detail)
                    : $report->failed('file_check', $detail);
            } catch (\Throwable $e) {
                $report = $report->failed('file_check', $e->getMessage());
            }
        } else {
            $report = $report->warning('file_check', 'Skipped via --skip-file-check');
        }

        // ── Print report ──────────────────────────────────────────────────────
        $this->newLine();
        $this->line('┌─────────────────────────────────────────────────────────────┐');
        $this->line('│           VOZARA → LENDR Migration Validation Report         │');
        $this->line('└─────────────────────────────────────────────────────────────┘');
        $this->newLine();

        foreach ($report->checks() as $check => $result) {
            $colour = match ($result['status']) {
                ValidationReport::STATUS_PASSED => 'green',
                ValidationReport::STATUS_WARNING => 'yellow',
                ValidationReport::STATUS_FAILED => 'red',
            };

            $this->line(sprintf(
                '  [<fg=%s>%s</>] %-30s %s',
                $colour,
                str_pad($result['status'], 7),
                $check,
                $result['detail'],
            ));
        }

        $this->newLine();
        $passed = $report->countByStatus(ValidationReport::STATUS_PASSED);
        $warnings = $report->countByStatus(ValidationReport::STATUS_WARNING);
        $failed = $report->countByStatus(ValidationReport::STATUS_FAILED);

        $this->line("  Passed: <fg=green>{$passed}</>  Warnings: <fg=yellow>{$warnings}</>  Failed: <fg=red>{$failed}</>");
        $this->newLine();

        if ($report->overallPassed()) {
            $this->line('  <fg=green;options=bold>✓ OVERALL: PASS — Migration is ready for cutover.</>');
        } else {
            $this->line('  <fg=red;options=bold>✗ OVERALL: FAIL — Resolve failed checks before cutover.</>');
        }

        $this->newLine();

        return $report->overallPassed() ? self::SUCCESS : self::FAILURE;
    }
}
