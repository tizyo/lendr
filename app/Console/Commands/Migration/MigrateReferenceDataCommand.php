<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:reference-data
 *
 * Migrates: loan_type, loan_plan, expense_categories,
 *           exchange_rates, system_settings from VOZARA.
 */
class MigrateReferenceDataCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:reference-data
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA reference data (loan types, plans, expense categories, settings)';

    public function handle(): int
    {
        $svc = $this->makeService();
        $dryRun = $this->isDryRun();
        $errors = [];
        $migrated = 0;
        $skipped = 0;

        // Resolve the legacy connection once and reuse it across every
        // section below, rather than re-resolving per section - repeated
        // connect attempts against an unreachable host within a single run
        // add unnecessary latency and, in some environments, can leave the
        // driver in a bad state after several failed reconnects in a row.
        try {
            $legacy = $svc->legacy();
        } catch (\Throwable $e) {
            $this->printResult(new MigrationResult(
                step: 'reference-data',
                migrated: 0,
                skipped: 0,
                failed: 1,
                dryRun: $dryRun,
                errors: ['legacy connection: '.$e->getMessage()],
            ));

            return self::FAILURE;
        }

        // ── Loan types ────────────────────────────────────────────────────────
        $this->info('→ Migrating loan types…');

        try {
            $rows = $legacy->table('loan_type')->get();

            foreach ($rows as $row) {
                if ($svc->alreadyMigrated('loan_types', $row->id)) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $newId = DB::table('loan_types')->insertGetId([
                        'name' => $row->name,
                        'description' => $row->description ?? null,
                        'is_active' => (bool) ($row->status ?? 1),
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                    $svc->logSuccess('loan_types', $row->id, $newId);
                }
                $migrated++;
            }
        } catch (\Throwable $e) {
            $errors[] = 'loan_types: '.$e->getMessage();
        }

        // ── Loan plans ────────────────────────────────────────────────────────
        $this->info('→ Migrating loan plans…');

        try {
            $rows = $legacy->table('loan_plan')->get();

            foreach ($rows as $row) {
                if ($svc->alreadyMigrated('loan_plans', $row->id)) {
                    $skipped++;

                    continue;
                }

                $loanTypeNewId = $svc->newId('loan_types', (int) $row->loan_type_id);

                if (! $loanTypeNewId) {
                    $errors[] = "loan_plans: no mapped loan_type for legacy id={$row->loan_type_id}";

                    continue;
                }

                if (! $dryRun) {
                    $newId = DB::table('loan_plans')->insertGetId([
                        'loan_type_id' => $loanTypeNewId,
                        'name' => $row->name,
                        'interest_rate' => $row->interest_rate,
                        'interest_type' => $row->interest_type ?? 'flat',
                        'repayment_frequency' => $row->repayment_period ?? 'monthly',
                        'max_amount' => $row->max_amount ?? null,
                        'min_amount' => $row->min_amount ?? null,
                        'max_duration_months' => $row->max_duration ?? null,
                        'min_duration_months' => $row->min_duration ?? null,
                        'processing_fee_rate' => $row->processing_fee ?? 0,
                        'penalty_rate' => $row->penalty_rate ?? 0,
                        'grace_period_days' => $row->grace_period ?? 0,
                        'is_active' => (bool) ($row->status ?? 1),
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                    $svc->logSuccess('loan_plans', $row->id, $newId);
                }
                $migrated++;
            }
        } catch (\Throwable $e) {
            $errors[] = 'loan_plans: '.$e->getMessage();
        }

        // ── Expense categories ─────────────────────────────────────────────────
        $this->info('→ Migrating expense categories…');

        try {
            $rows = $legacy->table('expense_categories')->get();

            foreach ($rows as $row) {
                if ($svc->alreadyMigrated('expense_categories', $row->id)) {
                    $skipped++;

                    continue;
                }

                if (! $dryRun) {
                    $newId = DB::table('expense_categories')->insertGetId([
                        'name' => $row->name,
                        'code' => $row->code ?? strtoupper(substr(preg_replace('/\s+/', '_', $row->name), 0, 20)),
                        'is_active' => true,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);
                    $svc->logSuccess('expense_categories', $row->id, $newId);
                }
                $migrated++;
            }
        } catch (\Throwable $e) {
            $errors[] = 'expense_categories: '.$e->getMessage();
        }

        // ── System settings ────────────────────────────────────────────────────
        $this->info('→ Migrating system settings…');

        try {
            $rows = $legacy->table('settings')->get();

            foreach ($rows as $row) {
                if (! $dryRun) {
                    DB::table('settings')->updateOrInsert(
                        ['key' => $row->setting_key ?? $row->key],
                        ['value' => $row->setting_value ?? $row->value],
                    );
                }
                $migrated++;
            }
        } catch (\Throwable $e) {
            $errors[] = 'settings: '.$e->getMessage();
        }

        $result = new MigrationResult(
            step: 'reference-data',
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
