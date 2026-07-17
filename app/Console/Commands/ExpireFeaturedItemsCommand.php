<?php

namespace App\Console\Commands;

use App\Models\FeaturedRepoItem;
use App\Models\HotDeal;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * lendr:expire-featured-items
 *
 * Deactivates paid featured repo item slots and hot deals whose
 * expiry timestamp has passed. Designed to run every hour or daily.
 */
class ExpireFeaturedItemsCommand extends Command
{
    protected $signature   = 'lendr:expire-featured-items {--dry-run : Report without making changes}';
    protected $description = 'Deactivate expired featured repo items and hot deals';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // ── Featured Repo Item Slots ───────────────────────────────────────
        $expiredSlots = FeaturedRepoItem::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $this->info("Found {$expiredSlots->count()} expired featured slot(s).");

        foreach ($expiredSlots as $slot) {
            $this->line("  • Slot #{$slot->id} — item #{$slot->repo_item_id} (tenant: {$slot->tenant_id}) expired at {$slot->expires_at}");

            if (! $dryRun) {
                $slot->update(['is_active' => false]);

                Log::info('[FeaturedItems] Slot expired and deactivated', [
                    'slot_id'      => $slot->id,
                    'repo_item_id' => $slot->repo_item_id,
                    'tenant_id'    => $slot->tenant_id,
                    'expired_at'   => $slot->expires_at,
                ]);
            }
        }

        // ── Hot Deals ─────────────────────────────────────────────────────
        $expiredDeals = HotDeal::where('is_active', true)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        $this->info("Found {$expiredDeals->count()} expired hot deal(s).");

        foreach ($expiredDeals as $deal) {
            $this->line("  • Deal #{$deal->id} \"{$deal->title}\" (tenant: {$deal->tenant_id}) expired at {$deal->expires_at}");

            if (! $dryRun) {
                $deal->update(['is_active' => false]);

                Log::info('[HotDeals] Deal expired and deactivated', [
                    'deal_id'    => $deal->id,
                    'title'      => $deal->title,
                    'tenant_id'  => $deal->tenant_id,
                    'expired_at' => $deal->expires_at,
                ]);
            }
        }

        $totalExpired = $expiredSlots->count() + $expiredDeals->count();

        if ($dryRun) {
            $this->warn("Dry run — no changes made. Would have expired {$totalExpired} record(s).");
        } else {
            $this->info("Done. Expired {$totalExpired} record(s).");
        }

        return self::SUCCESS;
    }
}
