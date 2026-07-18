<?php

namespace App\Console\Commands\Migration;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * migration:vozara:cutover
 *
 * Final cutover sequence:
 *  1. Run incremental sync (re-runs each migration step to pick up new records since last full run)
 *  2. Run the validation suite
 *  3. If validation PASSES: update tenant status to 'active'
 *  4. If validation FAILS:  abort and display errors
 */
class MigrateCutoverCommand extends Command
{
    protected $signature = 'migration:vozara:cutover
                            {--tenant= : Target tenant ID}
                            {--force : Proceed with cutover even if validation has warnings}
                            {--skip-file-check : Skip S3 file check during validation}';

    protected $description = 'Run final VOZARA → LENDR incremental sync, validate, and activate tenant';

    public function handle(): int
    {
        $tenantId = $this->option('tenant')
            ?? DB::table('tenants')->orderBy('id')->value('id');

        if (! $tenantId) {
            $this->error('No tenants found. Pass --tenant=<id>.');

            return self::FAILURE;
        }

        $this->line("Starting VOZARA cutover for tenant: <comment>{$tenantId}</comment>");
        $this->newLine();

        // ── Step 1: Incremental sync ───────────────────────────────────────────
        $this->info('Step 1/3 — Running incremental migration sync…');

        $steps = [
            'migration:vozara:reference-data',
            'migration:vozara:users',
            'migration:vozara:borrowers',
            'migration:vozara:loans',
            'migration:vozara:schedules',
            'migration:vozara:payments',
            'migration:vozara:funds',
            'migration:vozara:expenses',
            'migration:vozara:documents',
        ];

        foreach ($steps as $step) {
            $this->line("  → {$step}");
            $exit = Artisan::call($step, [
                '--tenant' => $tenantId,
                '--batch' => 200,
            ]);

            if ($exit !== 0) {
                $this->error("Step {$step} failed. Aborting cutover.");

                return self::FAILURE;
            }
        }

        $this->line('  <fg=green>Incremental sync complete.</>');
        $this->newLine();

        // ── Step 2: Validation ─────────────────────────────────────────────────
        $this->info('Step 2/3 — Running validation suite…');
        $this->newLine();

        $validateArgs = ['--tenant' => $tenantId];
        if ($this->option('skip-file-check')) {
            $validateArgs['--skip-file-check'] = true;
        }

        $validationExit = Artisan::call('migration:vozara:validate', $validateArgs);
        $this->line(Artisan::output());

        if ($validationExit !== 0) {
            $this->error('Validation FAILED. Cutover aborted. Fix all failed checks and retry.');

            return self::FAILURE;
        }

        // ── Step 3: Activate tenant ────────────────────────────────────────────
        $this->info('Step 3/3 — Activating tenant…');

        DB::table('tenants')
            ->where('id', $tenantId)
            ->update([
                'plan' => DB::table('tenants')->where('id', $tenantId)->value('plan') ?? 'starter',
                'status' => 'active',
                'updated_at' => now(),
            ]);

        // Log cutover event
        DB::table('migration_log')->insert([
            'tenant_id' => $tenantId,
            'table_name' => 'cutover',
            'legacy_id' => null,
            'new_id' => null,
            'status' => 'success',
            'notes' => 'Cutover completed successfully at '.now()->toDateTimeString(),
            'migrated_at' => now(),
        ]);

        $this->newLine();
        $this->line('<fg=green;options=bold>✓ Cutover complete! Tenant is now ACTIVE in LENDR.</>');
        $this->line("  Tenant ID: <comment>{$tenantId}</comment>");
        $this->line('  Staff users will be prompted to reset their passwords on first login.');
        $this->newLine();

        return self::SUCCESS;
    }
}
