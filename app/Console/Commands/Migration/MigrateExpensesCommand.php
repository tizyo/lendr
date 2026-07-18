<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:expenses
 *
 * Migrates all VOZARA expense tables:
 *  - expenses → expenses
 *  - budgets + expense_budgets → expense_budgets (deduplicated)
 *  - expense_approval_settings → expense_settings
 */
class MigrateExpensesCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:expenses
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA expense records, budgets, and approval settings';

    public function handle(): int
    {
        $svc = $this->makeService();
        $dryRun = $this->isDryRun();
        $batch = $this->batchSize();
        $errors = [];
        $migrated = 0;
        $skipped = 0;

        // ── Expenses ───────────────────────────────────────────────────────────
        $this->info('→ Migrating expenses…');

        try {
            $svc->legacy()->table('expenses')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('expenses', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    $categoryNewId = $svc->newId('expense_categories', (int) ($row->category_id ?? 0));

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('expenses')->insertGetId([
                                'expense_category_id' => $categoryNewId,
                                'title' => $row->title ?? $row->description ?? 'Migrated Expense',
                                'amount' => $row->amount,
                                'expense_date' => $row->expense_date ?? $row->date ?? $row->created_at,
                                'status' => $row->status ?? 'approved',
                                'description' => $row->description ?? $row->note ?? null,
                                'reference' => $row->reference ?? null,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('expenses', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "expense id={$row->id}: {$e->getMessage()}";
                        $svc->logFailed('expenses', $row->id, $e->getMessage());
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'expenses table: '.$e->getMessage();
        }

        // ── Budgets (merge budgets + expense_budgets) ──────────────────────────
        $this->info('→ Migrating budgets → expense_budgets (deduplicated)…');

        $budgetTables = array_filter(
            ['budgets', 'expense_budgets'],
            fn ($t) => $svc->legacy()->getSchemaBuilder()->hasTable($t),
        );

        foreach ($budgetTables as $table) {
            try {
                $svc->legacy()->table($table)->orderBy('id')->chunk($batch, function ($rows) use (
                    $svc, $dryRun, $table, &$migrated, &$skipped, &$errors
                ) {
                    foreach ($rows as $row) {
                        if ($svc->alreadyMigrated($table, $row->id)) {
                            $skipped++;

                            continue;
                        }

                        $categoryNewId = $svc->newId('expense_categories', (int) ($row->category_id ?? 0));
                        $period = $row->period ?? $row->month ?? now()->format('Y-m');

                        // Dedup: skip if budget for same category+period already exists
                        $existing = DB::table('expense_budgets')
                            ->where('expense_category_id', $categoryNewId)
                            ->where('period', $period)
                            ->exists();

                        if ($existing) {
                            $svc->logSkipped($table, $row->id, 'duplicate budget for category+period');
                            $skipped++;

                            continue;
                        }

                        try {
                            if (! $dryRun) {
                                $newId = DB::table('expense_budgets')->insertGetId([
                                    'expense_category_id' => $categoryNewId,
                                    'period' => $period,
                                    'amount' => $row->amount ?? $row->budget_amount ?? 0,
                                    'created_at' => $row->created_at ?? now(),
                                    'updated_at' => $row->updated_at ?? now(),
                                ]);
                                $svc->logSuccess($table, $row->id, $newId);
                            }
                            $migrated++;
                        } catch (\Throwable $e) {
                            $errors[] = "{$table} id={$row->id}: {$e->getMessage()}";
                        }
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = "{$table}: {$e->getMessage()}";
            }
        }

        // ── Expense approval settings ──────────────────────────────────────────
        $this->info('→ Migrating expense_approval_settings → expense_settings…');

        try {
            if ($svc->legacy()->getSchemaBuilder()->hasTable('expense_approval_settings')) {
                $settings = $svc->legacy()->table('expense_approval_settings')->get();

                foreach ($settings as $setting) {
                    if (! $dryRun) {
                        DB::table('expense_settings')->updateOrInsert(
                            ['key' => $setting->setting_key ?? $setting->key],
                            ['value' => $setting->setting_value ?? $setting->value],
                        );
                    }
                    $migrated++;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'expense_approval_settings: '.$e->getMessage();
        }

        $result = new MigrationResult(
            step: 'expenses',
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
