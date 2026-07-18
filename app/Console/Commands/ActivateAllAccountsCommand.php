<?php

namespace App\Console\Commands;

use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * php artisan accounts:activate
 *
 * Sets all tenants to status=active, email_verified_at=now, is_verified=true.
 * Then iterates every tenant schema and marks all users as is_active=true, email_verified_at=now.
 */
class ActivateAllAccountsCommand extends Command
{
    protected $signature = 'accounts:activate';

    protected $description = 'Activate all tenant accounts and mark all users as email verified';

    public function handle(): int
    {
        $this->newLine();

        // ── 1. Central tenants table ──────────────────────────────────────────
        $this->line('  Updating central tenants table…');

        $updated = DB::table('tenants')->update([
            'status' => 'active',
            'email_verified_at' => now(),
            'email_verification_token' => null,
            'is_verified' => true,
            'verified_at' => now(),
        ]);

        $this->line("  <fg=green>✓</> {$updated} tenant row(s) updated");
        $this->newLine();

        // ── 2. Per-tenant users tables ────────────────────────────────────────
        $this->line('  Updating users in each tenant schema…');

        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->line('  <fg=yellow>No tenants found.</>');
            $this->newLine();

            return self::SUCCESS;
        }

        $totalUsers = 0;

        foreach ($tenants as $tenant) {
            try {
                tenancy()->initialize($tenant);

                $users = DB::table('users')->update([
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);

                $totalUsers += $users;
                $this->line("  <fg=green>✓</> [{$tenant->id}] {$users} user(s) activated");
            } catch (\Throwable $e) {
                $this->line("  <fg=red>✗</> [{$tenant->id}] Failed: ".$e->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        $this->newLine();
        $this->line("  <fg=green;options=bold>Done.</> {$updated} tenant(s) + {$totalUsers} user(s) activated.");
        $this->newLine();

        return self::SUCCESS;
    }
}
