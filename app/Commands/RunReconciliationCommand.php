<?php

namespace App\Commands;

use App\Models\Tenant\BankStatement;
use App\Services\ReconciliationService;
use Illuminate\Console\Command;

/**
 * Bank statement import and reconciliation are separate manual admin
 * actions (upload CSV, then click "reconcile") — a statement can sit
 * imported-but-unmatched indefinitely if nobody takes the second step.
 * This auto-reconciles every statement still in 'pending' status so
 * matching happens without waiting on a manual click.
 *
 * Register: Schedule::command(RunReconciliationCommand::class)->dailyAt('04:00');
 */
class RunReconciliationCommand extends Command
{
    protected $signature = 'lendr:run-reconciliation';

    protected $description = 'Auto-reconcile any bank statements still pending after import';

    public function handle(ReconciliationService $service): int
    {
        $statements = BankStatement::where('status', 'pending')->get();

        if ($statements->isEmpty()) {
            $this->info('No pending bank statements to reconcile.');

            return self::SUCCESS;
        }

        foreach ($statements as $statement) {
            $result = $service->reconcile($statement);

            $this->info("Statement #{$statement->id} ({$statement->filename}): {$result['matched']} matched, {$result['unmatched']} unmatched.");
        }

        return self::SUCCESS;
    }
}
