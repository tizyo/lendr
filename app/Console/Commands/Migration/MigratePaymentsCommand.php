<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:payments
 *
 * Migrates VOZARA payment table → LENDR payments.
 * - Maps payment_method varchar → PaymentMethod enum
 * - Recalculates loan_balances from payments and compares to migrated values
 * - Flags discrepancies in migration_log notes
 */
class MigratePaymentsCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:payments
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA payment records and reconcile loan balances';

    public function handle(): int
    {
        $svc      = $this->makeService();
        $dryRun   = $this->isDryRun();
        $batch    = $this->batchSize();
        $errors   = [];
        $migrated = 0;
        $skipped  = 0;

        $this->info('→ Migrating payment → payments…');

        $svc->legacy()->table('payment')->orderBy('id')->chunk($batch, function ($rows) use (
            $svc, $dryRun, &$migrated, &$skipped, &$errors
        ) {
            foreach ($rows as $row) {
                if ($svc->alreadyMigrated('payments', $row->id)) {
                    $skipped++;
                    continue;
                }

                $loanNewId = $svc->newId('loan', (int) $row->loan_id)
                          ?? $svc->newId('loans', (int) $row->loan_id);

                if (! $loanNewId) {
                    $errors[] = "payment id={$row->id}: no mapped loan_id={$row->loan_id}";
                    $svc->logFailed('payments', $row->id, 'no mapped loan');
                    continue;
                }

                try {
                    if (! $dryRun) {
                        $newId = DB::table('payments')->insertGetId([
                            'loan_id'        => $loanNewId,
                            'amount'         => $row->pay_amount ?? $row->amount,
                            'payment_method' => $this->mapPaymentMethod($row->payment_method ?? 'cash'),
                            'payment_date'   => $row->pay_date ?? $row->payment_date ?? $row->created_at,
                            'reference'      => $row->reference ?? $row->receipt_no ?? null,
                            'note'           => $row->note ?? $row->remark ?? null,
                            'recorded_by'    => null, // no user mapping attempted for payments
                            'created_at'     => $row->created_at ?? now(),
                            'updated_at'     => $row->updated_at ?? now(),
                        ]);
                        $svc->logSuccess('payments', $row->id, $newId);
                    }
                    $migrated++;
                } catch (\Throwable $e) {
                    $errors[] = "payment id={$row->id}: {$e->getMessage()}";
                    $svc->logFailed('payments', $row->id, $e->getMessage());
                }
            }
        });

        // ── Reconcile loan balances ────────────────────────────────────────────
        if (! $dryRun) {
            $this->info('→ Reconciling loan balances from migrated payments…');
            $this->reconcileBalances($svc, $errors);
        }

        $result = new MigrationResult(
            step:     'payments',
            migrated: $migrated,
            skipped:  $skipped,
            failed:   count($errors),
            dryRun:   $dryRun,
            errors:   $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }

    private function reconcileBalances(object $svc, array &$errors): void
    {
        // For each migrated loan, sum its payments and compare to stored outstanding_balance
        $loans = DB::table('loans')->get(['id', 'total_payable', 'outstanding_balance', 'total_paid']);
        $discrepancies = 0;

        foreach ($loans as $loan) {
            $sumPaid = DB::table('payments')
                ->where('loan_id', $loan->id)
                ->sum('amount');

            $sumPaid = (string) $sumPaid;
            $expectedBalance = bcsub((string) $loan->total_payable, $sumPaid, 2);

            $diff = abs((float) bcsub($expectedBalance, (string) $loan->outstanding_balance, 2));

            if ($diff > 0.02) {
                // Flag discrepancy in migration_log
                DB::table('migration_log')->insert([
                    'tenant_id'   => $svc->tenantId(),
                    'table_name'  => 'balance_reconciliation',
                    'legacy_id'   => null,
                    'new_id'      => $loan->id,
                    'status'      => 'skipped',
                    'notes'       => "Balance discrepancy: stored={$loan->outstanding_balance} recalculated={$expectedBalance} diff={$diff}",
                    'migrated_at' => now(),
                ]);
                $discrepancies++;
            }
        }

        if ($discrepancies > 0) {
            $this->warn("  ! {$discrepancies} loan balance discrepancies flagged in migration_log (balance_reconciliation)");
        } else {
            $this->line('  <fg=green>All loan balances reconciled OK.</>');
        }
    }
}
