<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * migration:vozara:users
 *
 * Migrates the VOZARA users table into LENDR staff users.
 * - Passwords hashed with bcrypt("legacy_" . original_password)
 * - force_password_reset = true for all migrated users
 * - user_type int mapped to LENDR UserRole enum
 */
class MigrateUsersCommand extends BaseMigrationCommand
{
    protected $signature = 'migration:vozara:users
                            {--dry-run : Report without writing}
                            {--batch=100 : Chunk size}
                            {--tenant= : Target tenant ID}';

    protected $description = 'Migrate VOZARA staff users into LENDR (forces password reset on first login)';

    public function handle(): int
    {
        $svc      = $this->makeService();
        $dryRun   = $this->isDryRun();
        $batch    = $this->batchSize();
        $errors   = [];
        $migrated = 0;
        $skipped  = 0;

        $svc->legacy()->table('users')->orderBy('id')->chunk($batch, function ($rows) use (
            $svc, $dryRun, &$migrated, &$skipped, &$errors
        ) {
            foreach ($rows as $row) {
                if ($svc->alreadyMigrated('users', $row->id)) {
                    $skipped++;
                    continue;
                }

                try {
                    // Detect duplicate email in LENDR
                    $emailExists = DB::table('users')->where('email', $row->email)->exists();
                    if ($emailExists) {
                        $svc->logSkipped('users', $row->id, "email already exists: {$row->email}");
                        $skipped++;
                        continue;
                    }

                    if (! $dryRun) {
                        $newId = DB::table('users')->insertGetId([
                            'name'                  => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? $row->name ?? '')),
                            'email'                 => $row->email,
                            'phone'                 => $row->phone ?? null,
                            'password'              => Hash::make('legacy_' . ($row->password ?? 'vozara')),
                            'role'                  => $this->mapUserRole((int) ($row->user_type ?? 3)),
                            'is_active'             => (bool) ($row->status ?? 1),
                            'force_password_reset'  => true,
                            'email_verified_at'     => now(),
                            'created_at'            => $row->created_at ?? now(),
                            'updated_at'            => $row->updated_at ?? now(),
                        ]);
                        $svc->logSuccess('users', $row->id, $newId, "legacy role={$row->user_type}");
                    }
                    $migrated++;
                } catch (\Throwable $e) {
                    $errors[] = "users id={$row->id}: {$e->getMessage()}";
                    $svc->logFailed('users', $row->id, $e->getMessage());
                }
            }
        });

        $result = new MigrationResult(
            step:     'users',
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
