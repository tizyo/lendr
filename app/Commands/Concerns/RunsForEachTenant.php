<?php

namespace App\Commands\Concerns;

use App\Models\Landlord\Tenant;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs a tenant-level command's handle() once inside every live tenant.
 *
 * The scheduler runs commands on the central connection, which has no tenant
 * tables (loans, schedules, borrowers…) — without this, tenant commands fail
 * there instead of processing any tenant's data.
 *
 *   - already inside a tenant (e.g. `tenants:run`)       → runs for that tenant only
 *   - tenancy.commands_in_current_database = true (tests) → runs once, current DB
 *   - otherwise                                           → every active / trial tenant
 *
 * One tenant failing is reported and the rest still run.
 */
trait RunsForEachTenant
{
    /** Tenant statuses whose books are live (paused / cancelled workspaces are skipped). */
    protected array $tenantStatuses = ['active', 'trial'];

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (tenancy()->initialized || config('tenancy.commands_in_current_database')) {
            return (int) $this->laravel->call([$this, 'handle']);
        }

        $exitCode = self::SUCCESS;
        $ran = 0;

        foreach (Tenant::query()->whereIn('status', $this->tenantStatuses)->orderBy('created_at')->cursor() as $tenant) {
            $ran++;
            $this->line("<comment>▸ {$tenant->slug}</comment>");

            try {
                $result = (int) $tenant->run(fn () => $this->laravel->call([$this, 'handle']));
            } catch (\Throwable $e) {
                report($e);
                $this->error("  {$tenant->slug} failed: {$e->getMessage()}");
                $result = self::FAILURE;
            }

            if ($result !== self::SUCCESS) {
                $exitCode = $result;
            }
        }

        $this->info("Ran for {$ran} tenant(s).");

        return $exitCode;
    }
}
