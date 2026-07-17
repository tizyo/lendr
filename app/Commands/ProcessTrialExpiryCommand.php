<?php

namespace App\Commands;

use App\Mail\TrialExpiredMail;
use App\Mail\TrialExpiryWarningMail;
use App\Models\Landlord\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * lendr:process-trial-expiry
 *
 * Run daily. Does three things:
 *   1. Sends warning emails at 3 and 1 days before expiry.
 *   2. Marks expired trials as status=expired and sends expiry email.
 *
 * "expired" is a new status — tenants can still be reactivated to "active"
 * by the landlord after upgrading.
 */
class ProcessTrialExpiryCommand extends Command
{
    protected $signature   = 'lendr:process-trial-expiry';
    protected $description = 'Send trial expiry warnings and mark expired trial accounts.';

    public function handle(): int
    {
        $this->sendWarnings(3);
        $this->sendWarnings(1);
        $this->expireTrials();

        return self::SUCCESS;
    }

    // ── Warning emails ────────────────────────────────────────────────────────

    private function sendWarnings(int $daysAhead): void
    {
        $date = now()->addDays($daysAhead)->toDateString();

        $tenants = Tenant::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->whereDate('trial_ends_at', $date)
            ->get();

        foreach ($tenants as $tenant) {
            if (! $tenant->admin_email) continue;

            Mail::to($tenant->admin_email)
                ->queue(new TrialExpiryWarningMail($tenant, $daysAhead));

            $this->line("  ⚠  Warning ({$daysAhead}d) → {$tenant->name} <{$tenant->admin_email}>");
        }

        $this->info("Trial warnings ({$daysAhead} day): {$tenants->count()} sent.");
    }

    // ── Expiry ────────────────────────────────────────────────────────────────

    private function expireTrials(): void
    {
        $tenants = Tenant::where('status', 'trial')
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', now())
            ->get();

        foreach ($tenants as $tenant) {
            $tenant->update(['status' => 'expired']);

            if ($tenant->admin_email) {
                Mail::to($tenant->admin_email)
                    ->queue(new TrialExpiredMail($tenant));
            }

            $this->line("  ✗  Expired → {$tenant->name}");
        }

        $this->info("Trials expired: {$tenants->count()}.");
    }
}
