<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * migration:vozara:borrowers
 *
 * Migrates the VOZARA customers/borrowers table into LENDR borrowers.
 * - Deduplicates by phone number
 * - Migrates KYC boolean flags → kyc_document records (status=approved)
 * - Hashes borrower passwords
 * - Logs all merge actions
 */
class MigrateBorrowersCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:borrowers
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA customers/borrowers (deduplicates by phone, migrates KYC flags)';

    public function handle(): int
    {
        $svc = $this->makeService();
        $dryRun = $this->isDryRun();
        $batch = $this->batchSize();
        $errors = [];
        $migrated = 0;
        $skipped = 0;
        $merged = 0;

        // VOZARA may store borrowers in `customers` or `borrowers` table — try both
        $legacyTable = $svc->legacy()->getSchemaBuilder()->hasTable('borrowers')
            ? 'borrowers'
            : 'customers';

        $this->line("Using legacy table: <info>{$legacyTable}</info>");

        $svc->legacy()->table($legacyTable)->orderBy('id')->chunk($batch, function ($rows) use (
            $svc, $dryRun, $legacyTable, &$migrated, &$skipped, &$merged, &$errors
        ) {
            foreach ($rows as $row) {
                if ($svc->alreadyMigrated($legacyTable, $row->id)) {
                    $skipped++;

                    continue;
                }

                try {
                    // Normalise phone
                    $phone = $this->normalisePhone($row->phone ?? $row->mobile ?? '');

                    // Deduplication: check if borrower with same phone already in LENDR
                    $existing = DB::table('borrowers')->where('phone', $phone)->first();
                    if ($existing) {
                        $svc->logSkipped($legacyTable, $row->id, "duplicate phone merged into borrower_id={$existing->id}");
                        $skipped++;
                        $merged++;

                        continue;
                    }

                    if (! $dryRun) {
                        $newId = DB::table('borrowers')->insertGetId([
                            'first_name' => $row->first_name ?? ($row->name ? explode(' ', $row->name)[0] : 'Unknown'),
                            'last_name' => $row->last_name ?? (isset($row->name) ? (explode(' ', $row->name)[1] ?? '') : ''),
                            'email' => $row->email ?? null,
                            'phone' => $phone,
                            'national_id' => $row->national_id ?? $row->nrc ?? null,
                            'date_of_birth' => $row->dob ?? $row->date_of_birth ?? null,
                            'gender' => $row->gender ?? null,
                            'address' => $row->address ?? null,
                            'next_of_kin' => $row->next_of_kin ?? null,
                            'next_of_kin_phone' => $row->next_of_kin_phone ?? null,
                            'employment_status' => $row->employment_status ?? 'employed',
                            'employer_name' => $row->employer ?? $row->employer_name ?? null,
                            'monthly_income' => $row->monthly_income ?? $row->income ?? null,
                            'pin' => Hash::make($row->pin ?? $row->password ?? 'vozara'),
                            'is_active' => (bool) ($row->status ?? 1),
                            'is_blacklisted' => (bool) ($row->blacklisted ?? 0),
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]);
                        $svc->logSuccess($legacyTable, $row->id, $newId);

                        // Migrate KYC boolean flags → kyc_document records
                        $this->migrateKycFlags($row, $newId, $dryRun);
                    }
                    $migrated++;
                } catch (\Throwable $e) {
                    $errors[] = "{$legacyTable} id={$row->id}: {$e->getMessage()}";
                    $svc->logFailed($legacyTable, $row->id, $e->getMessage());
                }
            }
        });

        $this->line("<fg=cyan>Merged duplicates: {$merged}</>");

        $result = new MigrationResult(
            step: 'borrowers',
            migrated: $migrated,
            skipped: $skipped,
            failed: count($errors),
            dryRun: $dryRun,
            errors: $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }

    private function migrateKycFlags(object $row, int $borrowerId, bool $dryRun): void
    {
        $kycMap = [
            'national_id_verified' => 'national_id',
            'payslip_verified' => 'payslip',
            'bank_statement_verified' => 'bank_statement',
            'utility_bill_verified' => 'utility_bill',
            'photo_verified' => 'photo',
        ];

        foreach ($kycMap as $legacyField => $docType) {
            if (! empty($row->$legacyField)) {
                DB::table('kyc_documents')->insert([
                    'borrower_id' => $borrowerId,
                    'document_type' => $docType,
                    'status' => 'approved',
                    'file_path' => null, // files migrated separately by migration:vozara:documents
                    'notes' => 'Migrated from VOZARA legacy KYC flag',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function normalisePhone(string $phone): string
    {
        $phone = preg_replace('/\D/', '', $phone);

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return '+260'.substr($phone, 1);
        }

        if (str_starts_with($phone, '260') && strlen($phone) === 12) {
            return '+'.$phone;
        }

        if (str_starts_with($phone, '+')) {
            return $phone;
        }

        return $phone;
    }
}
