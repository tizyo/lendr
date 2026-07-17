<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * migration:vozara:loans
 *
 * Migrates VOZARA loan table (primary) and loans table (online portal).
 * - Maps status integer → LENDR LoanStatus enum
 * - Preserves or generates ref_no
 * - Tags online portal loans with source=online_portal
 */
class MigrateLoansCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:loans
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA loan records (primary + online portal) into LENDR loans';

    public function handle(): int
    {
        $svc      = $this->makeService();
        $dryRun   = $this->isDryRun();
        $batch    = $this->batchSize();
        $errors   = [];
        $migrated = 0;
        $skipped  = 0;

        // Determine legacy table names
        $primaryTable = 'loan';
        $portalTable  = $svc->legacy()->getSchemaBuilder()->hasTable('loans') ? 'loans' : null;

        foreach (array_filter([$primaryTable, $portalTable]) as $table) {
            $source = ($table === $portalTable) ? 'online_portal' : 'branch';
            $this->info("→ Migrating {$table} (source={$source})…");

            $svc->legacy()->table($table)->orderBy('id')->chunk($batch, function ($rows) use (
                $svc, $dryRun, $table, $source, &$migrated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    if ($svc->alreadyMigrated($table, $row->id)) {
                        $skipped++;
                        continue;
                    }

                    try {
                        // Map borrower
                        $borrowerNewId = $svc->newId('borrowers', (int) ($row->borrower_id ?? $row->customer_id));
                        if (! $borrowerNewId) {
                            $borrowerNewId = $svc->newId('customers', (int) ($row->borrower_id ?? $row->customer_id));
                        }

                        if (! $borrowerNewId) {
                            $errors[] = "{$table} id={$row->id}: no mapped borrower";
                            $svc->logFailed($table, $row->id, 'no mapped borrower');
                            continue;
                        }

                        // Map loan type + plan
                        $loanTypeNewId = $svc->newId('loan_types', (int) ($row->loan_type_id ?? 0));
                        $loanPlanNewId = $svc->newId('loan_plans', (int) ($row->loan_plan_id ?? 0));

                        // Generate ref_no if missing
                        $refNo = $row->ref_no ?? $row->loan_number ?? ('LN-' . strtoupper(Str::random(8)));

                        if (! $dryRun) {
                            $principal  = (string) ($row->amount ?? $row->principal_amount ?? 0);
                            $interest   = (string) ($row->interest_amount ?? 0);
                            $totalPayable = bcadd($principal, $interest, 2);

                            $newId = DB::table('loans')->insertGetId([
                                'borrower_id'         => $borrowerNewId,
                                'loan_type_id'        => $loanTypeNewId,
                                'loan_plan_id'        => $loanPlanNewId,
                                'ref_no'              => $refNo,
                                'principal_amount'    => $principal,
                                'interest_amount'     => $interest,
                                'total_payable'       => $row->total_payable ?? $totalPayable,
                                'outstanding_balance' => $row->balance ?? $row->outstanding_balance ?? $totalPayable,
                                'total_paid'          => $row->total_paid ?? 0,
                                'penalty_balance'     => $row->penalty ?? $row->penalty_balance ?? 0,
                                'duration_months'     => $row->duration ?? $row->duration_months ?? null,
                                'disbursement_date'   => $row->disbursement_date ?? $row->date_issued ?? null,
                                'maturity_date'       => $row->maturity_date ?? $row->due_date ?? null,
                                'status'              => $this->mapLoanStatus((int) ($row->status ?? 1)),
                                'source'              => $source,
                                'created_at'          => $row->created_at ?? now(),
                                'updated_at'          => $row->updated_at ?? now(),
                            ]);
                            $svc->logSuccess($table, $row->id, $newId, "ref_no={$refNo}");
                        }
                        $migrated++;
                    } catch (\Throwable $e) {
                        $errors[] = "{$table} id={$row->id}: {$e->getMessage()}";
                        $svc->logFailed($table, $row->id, $e->getMessage());
                    }
                }
            });
        }

        $result = new MigrationResult(
            step:     'loans',
            migrated: $migrated,
            skipped:  $skipped,
            failed:   count($errors),
            dryRun:   $dryRun,
            errors:   $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }
}
