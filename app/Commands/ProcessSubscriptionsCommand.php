<?php

namespace App\Commands;

use App\Mail\SubscriptionRenewalMail;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * lendr:process-subscriptions — run daily.
 *
 * A paid plan used to last for ever: payment set ends_at and nothing ever read it.
 * Now:
 *   1. 7, 3 and 1 days before ends_at the workspace admin is reminded to renew.
 *   2. Once ends_at plus a grace period has passed, the subscription is marked
 *      expired and the workspace moves back to Starter (nothing is deleted or
 *      locked; paid features switch off) and the admin is told.
 *
 * Only plans that came from that subscription are touched: if the landlord has
 * since put the workspace on another plan by hand, it is left alone.
 */
class ProcessSubscriptionsCommand extends Command
{
    public const REMINDER_DAYS = [7, 3, 1];

    public const GRACE_DAYS = 3;

    protected $signature = 'lendr:process-subscriptions {--dry-run : Report what would change without changing it}';

    protected $description = 'Remind workspaces before a paid plan ends and move expired ones back to Starter.';

    public function handle(): int
    {
        foreach (self::REMINDER_DAYS as $days) {
            $this->remind($days);
        }

        $this->expire();

        return self::SUCCESS;
    }

    private function remind(int $days): void
    {
        $due = Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->whereDate('ends_at', now()->addDays($days)->toDateString())
            ->get();

        foreach ($due as $subscription) {
            $tenant = Tenant::find($subscription->tenant_id);
            if (! $tenant?->admin_email || $this->option('dry-run')) {
                continue;
            }

            Mail::to($tenant->admin_email)->queue(new SubscriptionRenewalMail($tenant, $subscription, 'reminder', $days));
            $this->line("  ⚠  {$days}d reminder → {$tenant->name}");
        }

        $this->info("Renewal reminders ({$days} day): {$due->count()}.");
    }

    private function expire(): void
    {
        $lapsed = Subscription::where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', now()->subDays(self::GRACE_DAYS))
            ->get();

        foreach ($lapsed as $subscription) {
            $tenant = Tenant::find($subscription->tenant_id);

            if ($this->option('dry-run')) {
                $this->line("  would expire {$subscription->plan} → ".($tenant?->name ?? $subscription->tenant_id));

                continue;
            }

            $subscription->update(['status' => 'expired']);

            if (! $tenant || $tenant->plan !== $subscription->plan) {
                continue; // plan changed by hand since — the landlord's call stands
            }

            $tenant->update(['plan' => 'starter']);

            if ($tenant->admin_email) {
                Mail::to($tenant->admin_email)->queue(new SubscriptionRenewalMail($tenant, $subscription, 'ended'));
            }

            $this->line("  ✗  {$tenant->name}: {$subscription->plan} ended → starter");
        }

        $this->info("Subscriptions expired: {$lapsed->count()}.");
    }
}
