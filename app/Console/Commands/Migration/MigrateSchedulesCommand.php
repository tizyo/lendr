<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:schedules
 *
 * Migrates:
 *  - loan_schedule → repayment_schedules
 *  - loan_balance  → loan_balances
 *  - loan_status_log → loan_status_logs
 *
 * Generates missing schedule records from loan plan data where gaps exist.
 */
class MigrateSchedulesCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:schedules
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA loan schedules, balances, and status logs';

    public function handle(): int
    {
        $svc = $this->makeService();
        $dryRun = $this->isDryRun();
        $batch = $this->batchSize();
        $errors = [];
        $migrated = 0;
        $skipped = 0;

        // ── Repayment schedules ────────────────────────────────────────────────
        $this->info('→ Migrating loan_schedule → repayment_schedules…');

        try {
            $svc->legacy()->table('loan_schedule')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('repayment_schedules', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    $loanNewId = $svc->newId('loan', (int) $row->loan_id)
                              ?? $svc->newId('loans', (int) $row->loan_id);

                    if (! $loanNewId) {
                        $errors[] = "schedule id={$row->id}: no mapped loan";

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('repayment_schedules')->insertGetId([
                                'loan_id' => $loanNewId,
                                'instalment_no' => $row->instalment_no ?? $row->period ?? 1,
                                'due_date' => $row->due_date,
                                'principal_amount' => $row->principal ?? $row->principal_amount ?? 0,
                                'interest_amount' => $row->interest ?? $row->interest_amount ?? 0,
                                'total_due' => $row->instalment_amount ?? $row->total_due ?? 0,
                                'total_paid' => $row->paid_amount ?? $row->total_paid ?? 0,
                                'status' => $row->status ?? 'pending',
                                'paid_date' => $row->paid_date ?? null,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('repayment_schedules', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "schedule id={$row->id}: {$e->getMessage()}";
                        $svc->logFailed('repayment_schedules', $row->id, $e->getMessage());
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'loan_schedule table: '.$e->getMessage();
        }

        // ── Loan balances ──────────────────────────────────────────────────────
        $this->info('→ Migrating loan_balance → loan_balances…');

        try {
            $svc->legacy()->table('loan_balance')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('loan_balances', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    $loanNewId = $svc->newId('loan', (int) $row->loan_id)
                              ?? $svc->newId('loans', (int) $row->loan_id);

                    if (! $loanNewId) {
                        continue; // balance without loan — skip silently
                    }

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('loan_balances')->insertGetId([
                                'loan_id' => $loanNewId,
                                'outstanding_balance' => $row->outstanding_balance ?? $row->balance ?? 0,
                                'total_paid' => $row->total_paid ?? 0,
                                'penalty_balance' => $row->penalty ?? $row->penalty_balance ?? 0,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('loan_balances', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "loan_balance id={$row->id}: {$e->getMessage()}";
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'loan_balance table: '.$e->getMessage();
        }

        // ── Loan status logs ───────────────────────────────────────────────────
        $this->info('→ Migrating loan_status_log → loan_status_logs…');

        try {
            $svc->legacy()->table('loan_status_log')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('loan_status_logs', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    $loanNewId = $svc->newId('loan', (int) $row->loan_id)
                              ?? $svc->newId('loans', (int) $row->loan_id);

                    if (! $loanNewId) {
                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('loan_status_logs')->insertGetId([
                                'loan_id' => $loanNewId,
                                'from_status' => $this->mapLoanStatus((int) ($row->from_status ?? 0)),
                                'to_status' => $this->mapLoanStatus((int) ($row->to_status ?? 1)),
                                'note' => $row->note ?? $row->comment ?? null,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('loan_status_logs', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "status_log id={$row->id}: {$e->getMessage()}";
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'loan_status_log table: '.$e->getMessage();
        }

        $result = new MigrationResult(
            step: 'schedules',
            migrated: $migrated,
            skipped: $skipped,
            failed: count($errors),
            dryRun: $dryRun,
            errors: $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }
}
