<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * migration:vozara:documents
 *
 * Inventories all files in the VOZARA legacy uploads/ directory,
 * uploads them to S3, and updates file_path references in kyc_documents.
 *
 * Validates 20 random uploaded files are accessible.
 */
class MigrateDocumentsCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:documents
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}
                            {--source-path= : Path to VOZARA uploads directory (default: /var/www/vozara/uploads)}';

    protected $description = 'Migrate VOZARA document files to S3 and update kyc_document file_path references';

    public function handle(): int
    {
        $svc        = $this->makeService();
        $dryRun     = $this->isDryRun();
        $errors     = [];
        $migrated   = 0;
        $skipped    = 0;
        $sourcePath = $this->option('source-path') ?? '/var/www/vozara/uploads';

        // ── Inventory legacy files ─────────────────────────────────────────────
        $this->info("→ Scanning legacy uploads directory: {$sourcePath}");

        if (! is_dir($sourcePath)) {
            $this->warn("Source path does not exist: {$sourcePath}. Skipping document migration.");
            $this->warn("Set --source-path to the VOZARA uploads directory to enable this step.");

            return self::SUCCESS;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS)
        );

        $uploaded = [];

        foreach ($files as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relativePath = str_replace($sourcePath . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $s3Key        = 'vozara-migration/' . str_replace('\\', '/', $relativePath);

            // Check idempotency via migration_log using filename as legacy_ref
            if ($svc->alreadyMigrated('documents', 0)) {
                // Can't use numeric ID for files — check by s3Key in settings
                $alreadyUploaded = DB::table('migration_log')
                    ->where('tenant_id', $svc->tenantId())
                    ->where('table_name', 'documents')
                    ->where('legacy_ref', $s3Key)
                    ->where('status', 'success')
                    ->exists();

                if ($alreadyUploaded) {
                    $skipped++;
                    continue;
                }
            }

            try {
                if (! $dryRun) {
                    Storage::disk('s3')->put($s3Key, file_get_contents($file->getPathname()));

                    DB::table('migration_log')->insert([
                        'tenant_id'   => $svc->tenantId(),
                        'table_name'  => 'documents',
                        'legacy_id'   => null,
                        'new_id'      => null,
                        'legacy_ref'  => $s3Key,
                        'status'      => 'success',
                        'notes'       => "Uploaded to S3: {$s3Key}",
                        'migrated_at' => now(),
                    ]);

                    $uploaded[] = $s3Key;
                }
                $migrated++;
            } catch (\Throwable $e) {
                $errors[] = "file {$relativePath}: {$e->getMessage()}";
                DB::table('migration_log')->insert([
                    'tenant_id'   => $svc->tenantId(),
                    'table_name'  => 'documents',
                    'legacy_id'   => null,
                    'new_id'      => null,
                    'legacy_ref'  => $s3Key,
                    'status'      => 'failed',
                    'notes'       => $e->getMessage(),
                    'migrated_at' => now(),
                ]);
            }
        }

        // ── Update kyc_document file_path references ───────────────────────────
        if (! $dryRun && count($uploaded) > 0) {
            $this->info('→ Linking uploaded files to kyc_documents records…');
            $this->linkDocuments($sourcePath, $uploaded);
        }

        // ── Validate 20 random uploaded files ─────────────────────────────────
        if (! $dryRun && count($uploaded) > 0) {
            $this->validateSample($uploaded, $errors);
        }

        $result = new MigrationResult(
            step:     'documents',
            migrated: $migrated,
            skipped:  $skipped,
            failed:   count($errors),
            dryRun:   $dryRun,
            errors:   $errors,
        );

        $this->printResult($result);

        return $result->isSuccess() ? self::SUCCESS : self::FAILURE;
    }

    private function linkDocuments(string $sourcePath, array $uploaded): void
    {
        // For each kyc_document with no file_path, attempt to find a matching uploaded file
        $docs = DB::table('kyc_documents')->whereNull('file_path')->get();

        foreach ($docs as $doc) {
            // Heuristic: match by borrower_id and document_type in S3 key
            $match = collect($uploaded)->first(
                fn ($key) => str_contains($key, (string) $doc->borrower_id)
                          && str_contains(strtolower($key), str_replace('_', '-', $doc->document_type))
            );

            if ($match) {
                $s3Url = Storage::disk('s3')->url($match);
                DB::table('kyc_documents')->where('id', $doc->id)->update(['file_path' => $s3Url]);
            }
        }
    }

    private function validateSample(array $uploaded, array &$errors): void
    {
        $this->info('→ Validating 20 random uploaded S3 files…');

        $sample = collect($uploaded)->shuffle()->take(20);
        $failed = 0;

        foreach ($sample as $key) {
            try {
                $exists = Storage::disk('s3')->exists($key);
                if (! $exists) {
                    $errors[] = "S3 validation failed: {$key} not found";
                    $failed++;
                }
            } catch (\Throwable $e) {
                $errors[] = "S3 validation error for {$key}: {$e->getMessage()}";
                $failed++;
            }
        }

        if ($failed === 0) {
            $this->line('  <fg=green>All sampled S3 files validated OK.</>');
        } else {
            $this->warn("  ! {$failed}/{$sample->count()} S3 file checks failed");
        }
    }
}
