<?php

namespace App\Commands;

use App\Jobs\ProcessAutoDebitJob;
use App\Models\Landlord\Tenant;
use App\Models\Landlord\TenantWallet;
use App\Models\Tenant\StandingOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * lendr:process-standing-orders
 *
 * Runs daily (e.g. 06:00). For every Enterprise tenant with an active, debit-enabled wallet:
 *   1. Find pending standing orders due today (or overdue with retry window open).
 *   2. Dispatch ProcessAutoDebitJob for each.
 *
 * Retry logic: after failure, StandingOrder::recordFailure() sets next_attempt_at = now()+N days.
 * This command picks up orders where next_attempt_at <= now() OR next_attempt_at IS NULL and due_date <= today.
 */
class ProcessStandingOrdersCommand extends Command
{
    protected $signature = 'lendr:process-standing-orders {--dry-run : Preview without dispatching jobs}';

    protected $description = 'Process due standing orders for auto-debit repayments (Enterprise only)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today = now()->toDateString();
        $dispatched = 0;
        $skipped = 0;

        $this->info($dryRun ? '[DRY RUN] Processing standing orders…' : 'Processing standing orders…');

        // Only Enterprise tenants with active debit-enabled wallets
        $wallets = TenantWallet::where('is_active', true)
            ->where('debit_enabled', true)
            ->get();

        if ($wallets->isEmpty()) {
            $this->info('No active debit wallets found.');

            return self::SUCCESS;
        }

        foreach ($wallets as $wallet) {
            $tenant = Tenant::find($wallet->tenant_id);

            if (! $tenant || $tenant->plan !== 'enterprise') {
                $skipped++;

                continue;
            }

            if ($tenant->status !== 'active') {
                $skipped++;

                continue;
            }

            $this->line("  → Tenant: {$tenant->name} [{$tenant->id}] via {$wallet->gateway}");

            try {
                // Initialize tenant context to access tenant DB tables
                tenancy()->initialize($tenant);

                $orders = StandingOrder::where('status', 'pending')
                    ->where(function ($q) use ($today) {
                        $q->whereNull('next_attempt_at')
                            ->where('due_date', '<=', $today);
                    })
                    ->orWhere(function ($q) {
                        $q->where('status', 'pending')
                            ->where('next_attempt_at', '<=', now());
                    })
                    ->get();

                $this->line("    Found {$orders->count()} due order(s).");

                foreach ($orders as $order) {
                    if (! $dryRun) {
                        ProcessAutoDebitJob::dispatch($order, $wallet->id);
                    }
                    $dispatched++;
                }
            } catch (\Throwable $e) {
                Log::error('[ProcessStandingOrders] Tenant error', [
                    'tenant_id' => $wallet->tenant_id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("  Error for tenant {$wallet->tenant_id}: ".$e->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        $this->info("Done. Dispatched: {$dispatched}, Skipped: {$skipped} tenants.");
        Log::info('[ProcessStandingOrders] Complete', compact('dispatched', 'skipped', 'dryRun'));

        return self::SUCCESS;
    }
}
