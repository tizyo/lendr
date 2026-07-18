<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:rollback
 *
 * Reverses ALL migrated data in the LENDR tenant schema by reading
 * migration_log new_id values and deleting those exact rows.
 *
 * The VOZARA legacy database is NEVER touched.
 * Rollback is ordered to respect foreign key constraints.
 */
class MigrateRollbackCommand extends Command
{
    protected $signature = 'migration:vozara:rollback
                            {--tenant= : Target tenant ID}
                            {--step= : Roll back only a specific step (e.g. payments)}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reverse all VOZARA migration data in LENDR (legacy DB untouched)';

    /** Rollback order respects FK constraints — children before parents */
    private const ROLLBACK_STEPS = [
        ['log_table' => 'documents',              'lendr_table' => null],          // files: no DB rows to delete
        ['log_table' => 'fund_transactions_legacy', 'lendr_table' => 'fund_transactions'],
        ['log_table' => 'fund_deposits',           'lendr_table' => 'fund_transactions'],
        ['log_table' => 'fund_balances',           'lendr_table' => 'fund_balances'],
        ['log_table' => 'expense_budgets',         'lendr_table' => 'expense_budgets'],
        ['log_table' => 'budgets',                 'lendr_table' => 'expense_budgets'],
        ['log_table' => 'expenses',                'lendr_table' => 'expenses'],
        ['log_table' => 'repayment_schedules',     'lendr_table' => 'repayment_schedules'],
        ['log_table' => 'loan_status_logs',        'lendr_table' => 'loan_status_logs'],
        ['log_table' => 'loan_balances',           'lendr_table' => 'loan_balances'],
        ['log_table' => 'payments',                'lendr_table' => 'payments'],
        ['log_table' => 'loan',                    'lendr_table' => 'loans'],
        ['log_table' => 'loans',                   'lendr_table' => 'loans'],
        ['log_table' => 'balance_reconciliation',  'lendr_table' => null],          // log-only entry
        ['log_table' => 'customers',               'lendr_table' => 'borrowers'],
        ['log_table' => 'borrowers',               'lendr_table' => 'borrowers'],
        ['log_table' => 'users',                   'lendr_table' => 'users'],
        ['log_table' => 'expense_categories',      'lendr_table' => 'expense_categories'],
        ['log_table' => 'loan_plans',              'lendr_table' => 'loan_plans'],
        ['log_table' => 'loan_types',              'lendr_table' => 'loan_types'],
    ];

    public function handle(): int
    {
        $tenantId = $this->option('tenant')
            ?? DB::table('tenants')->orderBy('id')->value('id');

        if (! $tenantId) {
            $this->error('No tenants found. Pass --tenant=<id>.');

            return self::FAILURE;
        }

        $stepFilter = $this->option('step');

        if (! $this->option('force')) {
            $scope = $stepFilter ? "step={$stepFilter}" : 'ALL STEPS';
            if (! $this->confirm(
                "This will DELETE all VOZARA-migrated data for tenant={$tenantId} ({$scope}). Proceed?",
                false,
            )) {
                $this->line('Rollback cancelled.');

                return self::SUCCESS;
            }
        }

        $svc = new MigrationService((string) $tenantId);
        $deleted = 0;

        $steps = $stepFilter
            ? array_filter(self::ROLLBACK_STEPS, fn ($s) => $s['log_table'] === $stepFilter)
            : self::ROLLBACK_STEPS;

        foreach ($steps as $step) {
            $logTable = $step['log_table'];
            $lendrTable = $step['lendr_table'];

            // Collect new_ids from migration_log for this step
            $newIds = DB::table('migration_log')
                ->where('tenant_id', $tenantId)
                ->where('table_name', $logTable)
                ->where('status', 'success')
                ->whereNotNull('new_id')
                ->pluck('new_id')
                ->unique()
                ->all();

            if (empty($newIds) || ! $lendrTable) {
                // Clean log entry even if no rows to delete
                $svc->clearLog($logTable);

                continue;
            }

            try {
                $count = DB::table($lendrTable)->whereIn('id', $newIds)->delete();
                $this->line("  Deleted {$count} rows from <info>{$lendrTable}</info> (log_table={$logTable})");
                $deleted += $count;
            } catch (\Throwable $e) {
                $this->error("  Failed to delete from {$lendrTable}: {$e->getMessage()}");
            }

            $svc->clearLog($logTable);
        }

        // Clear cutover log entry if full rollback
        if (! $stepFilter) {
            DB::table('migration_log')
                ->where('tenant_id', $tenantId)
                ->where('table_name', 'cutover')
                ->delete();

            // Revert tenant status
            DB::table('tenants')->where('id', $tenantId)->update(['status' => 'pending']);
        }

        $this->newLine();
        $this->line("<fg=yellow>Rollback complete. {$deleted} total rows removed from LENDR.</>");
        $this->line('The VOZARA legacy database was NOT modified.');
        $this->newLine();

        return self::SUCCESS;
    }
}
