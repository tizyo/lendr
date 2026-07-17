<?php

namespace App\Commands;

use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\DbDumper\Databases\MySql;

/**
 * spatie/laravel-backup only knows how to dump statically-configured
 * connections, but each tenant lives in its own dynamically-named MySQL
 * database (see config/tenancy.php) — this dumps every tenant database
 * individually and uploads it alongside the landlord backup produced by
 * `backup:run`.
 *
 * Register: Schedule::command(BackupTenantDatabasesCommand::class)->dailyAt('02:00');
 */
class BackupTenantDatabasesCommand extends Command
{
    protected $signature   = 'lendr:backup-tenant-databases';
    protected $description = 'Dump every tenant database and upload it to the backup disk';

    public function handle(): int
    {
        $disk       = config('backup.backup.destination.disks')[0] ?? 'local';
        $connection = config('database.connections.mysql');
        $date       = now()->format('Y-m-d_His');
        $failures   = 0;

        $tenants = Tenant::all();
        $this->info("Backing up {$tenants->count()} tenant database(s) to disk [{$disk}]…");

        foreach ($tenants as $tenant) {
            $dbName = $tenant->database()->getName();

            try {
                $tempPath = storage_path("app/backup-temp/{$dbName}-{$date}.sql.gz");

                if (! is_dir(dirname($tempPath))) {
                    mkdir(dirname($tempPath), 0755, true);
                }

                MySql::create()
                    ->setHost($connection['host'])
                    ->setPort((int) $connection['port'])
                    ->setDbName($dbName)
                    ->setUserName($connection['username'])
                    ->setPassword($connection['password'])
                    ->useCompressor(new \Spatie\DbDumper\Compressors\GzipCompressor())
                    ->dumpToFile($tempPath);

                $remotePath = "LENDR/tenants/{$tenant->id}/{$dbName}-{$date}.sql.gz";
                Storage::disk($disk)->put($remotePath, fopen($tempPath, 'r'));

                unlink($tempPath);

                $this->line("  ✓ {$dbName}");
            } catch (\Throwable $e) {
                $failures++;
                $this->error("  ✗ {$dbName}: {$e->getMessage()}");
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} tenant backup(s) failed.");

            return self::FAILURE;
        }

        $this->info('All tenant database backups completed.');

        return self::SUCCESS;
    }
}
