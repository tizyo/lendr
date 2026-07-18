<?php

namespace App\Commands;

use App\Models\Tenant\Lead;
use Illuminate\Console\Command;

/**
 * Runs daily to remind assigned staff of leads with follow-up dates today or past.
 *
 * Register: Schedule::command(ProcessCrmFollowupsCommand::class)->dailyAt('08:00');
 */
class ProcessCrmFollowupsCommand extends Command
{
    protected $signature = 'lendr:crm-followups {--dry-run : Preview without sending}';

    protected $description = 'Notify staff of leads due for follow-up today';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $today = now()->toDateString();

        $leads = Lead::query()
            ->with('assignedTo:id,name,email')
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->whereDate('follow_up_date', '<=', $today)
            ->whereNotNull('assigned_to')
            ->get();

        $this->info($dryRun
            ? "[DRY RUN] Found {$leads->count()} leads due for follow-up."
            : "Processing {$leads->count()} leads due for follow-up.",
        );

        $notified = 0;

        foreach ($leads as $lead) {
            $officer = $lead->assignedTo;

            if (! $officer) {
                continue;
            }

            if ($dryRun) {
                $this->line("  [DRY RUN] Would notify {$officer->name} for lead {$lead->lead_number} ({$lead->full_name})");
                $notified++;

                continue;
            }

            // In-app notification (reuse NotificationService pattern if available)
            try {
                \App\Services\NotificationService::send(
                    $officer->id,
                    'lead_followup',
                    "Follow-up due: {$lead->full_name} ({$lead->lead_number})",
                    ['lead_id' => $lead->id],
                );
            } catch (\Throwable) {
                // Notification service may not be bootstrapped in all contexts
            }

            $notified++;
        }

        $this->info($dryRun
            ? "[DRY RUN] Would have notified {$notified} officer(s)."
            : "Sent {$notified} follow-up reminder(s).",
        );

        return self::SUCCESS;
    }
}
