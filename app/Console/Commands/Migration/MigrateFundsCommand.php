<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:funds
 *
 * Migrates:
 *  - fund_balance    → fund_balances
 *  - fund_deposits   → fund_transactions (type=deposit)
 *  - fund_transactions → fund_transactions
 *  - disbursement_funds → fund_transactions (type=disbursement)
 *
 * Reconciles: recalculated balance must equal stored balance ± 0.01
 */
class MigrateFundsCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:funds
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA fund balances, deposits, and disbursements';

    public function handle(): int
    {
        $svc = $this->makeService();
        $dryRun = $this->isDryRun();
        $batch = $this->batchSize();
        $errors = [];
        $migrated = 0;
        $skipped = 0;

        // ── Fund balance snapshot ──────────────────────────────────────────────
        $this->info('→ Migrating fund_balance → fund_balances…');

        try {
            $balanceRow = $svc->legacy()->table('fund_balance')->first();

            if ($balanceRow && ! $dryRun) {
                $exists = DB::table('fund_balances')->exists();
                if (! $exists) {
                    DB::table('fund_balances')->insert([
                        'available_balance' => $balanceRow->available_balance ?? $balanceRow->balance ?? 0,
                        'total_disbursed' => $balanceRow->total_disbursed ?? 0,
                        'total_collected' => $balanceRow->total_collected ?? 0,
                        'created_at' => $balanceRow->created_at ?? now(),
                        'updated_at' => $balanceRow->updated_at ?? now(),
                    ]);
                    $migrated++;
                } else {
                    $skipped++;
                }
            }
        } catch (\Throwable $e) {
            $errors[] = 'fund_balance: '.$e->getMessage();
        }

        // ── Fund deposits ──────────────────────────────────────────────────────
        $this->info('→ Migrating fund_deposits → fund_transactions…');

        try {
            $svc->legacy()->table('fund_deposits')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('fund_deposits', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('fund_transactions')->insertGetId([
                                'type' => 'deposit',
                                'amount' => $row->amount,
                                'description' => $row->description ?? $row->note ?? 'VOZARA migrated deposit',
                                'reference' => $row->reference ?? null,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('fund_deposits', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "fund_deposit id={$row->id}: {$e->getMessage()}";
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'fund_deposits table: '.$e->getMessage();
        }

        // ── Fund transactions ──────────────────────────────────────────────────
        $this->info('→ Migrating fund_transactions…');

        try {
            $svc->legacy()->table('fund_transactions')->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated('fund_transactions_legacy', $row->id)) {
                        $skipped++;

                        continue;
                    }

                    try {
                        if (! $dryRun) {
                            $newId = DB::table('fund_transactions')->insertGetId([
                                'type' => $row->transaction_type ?? $row->type ?? 'credit',
                                'amount' => $row->amount,
                                'description' => $row->description ?? $row->note ?? null,
                                'reference' => $row->reference ?? null,
                                'created_at' => $row->created_at ?? now(),
                                'updated_at' => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess('fund_transactions_legacy', $row->id, $newId);
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "fund_transaction id={$row->id}: {$e->getMessage()}";
                    }
                }
            });
        } catch (\Throwable $e) {
            $errors[] = 'fund_transactions table: '.$e->getMessage();
        }

        // ── Balance reconciliation ─────────────────────────────────────────────
        if (! $dryRun) {
            $this->reconcile($svc, $errors);
        }

        $result = new MigrationResult(
            step: 'funds',
            migrated: $migrated,
            skipped: $skipped,
            failed: count($errors),
            dryRun: $dryRun,
            errors: $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }

    private function reconcile(object $svc, array &$errors): void
    {
        $balance = DB::table('fund_balances')->first();
        if (! $balance) {
            return;
        }

        $deposits = DB::table('fund_transactions')->where('type', 'deposit')->sum('amount');
        $disbursements = DB::table('fund_transactions')->where('type', 'disbursement')->sum('amount');
        $collections = DB::table('payments')->sum('amount');

        $recalculated = bcadd(bcadd((string) $deposits, (string) $collections, 2), '0', 2);
        $recalculated = bcsub($recalculated, (string) $disbursements, 2);

        $diff = abs((float) bcsub($recalculated, (string) $balance->available_balance, 2));

        if ($diff > 0.01) {
            $errors[] = "Fund balance discrepancy: stored={$balance->available_balance} recalculated={$recalculated} diff={$diff}";
            $this->warn("  ! Fund balance discrepancy of {$diff} detected — review migration_log");
        } else {
            $this->line('  <fg=green>Fund balance reconciled OK (diff='.number_format($diff, 4).').</>');
        }
    }
}
