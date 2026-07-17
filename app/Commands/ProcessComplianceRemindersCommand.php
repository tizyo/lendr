<?php

namespace App\Commands;

use App\Models\Tenant\ComplianceEvent;
use Illuminate\Console\Command;

class ProcessComplianceRemindersCommand extends Command
{
    protected $signature   = 'lendr:compliance-reminders {--days=7 : Days ahead to send reminders}';
    protected $description = 'Mark overdue compliance events and flag upcoming ones for reminder';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        // Mark overdue
        $overdue = ComplianceEvent::where('status', 'pending')
            ->whereDate('due_date', '<', now()->toDateString())
            ->get();

        foreach ($overdue as $event) {
            $event->update(['status' => 'overdue']);
        }

        // Flag upcoming for reminder (within $days days, not yet sent)
        $upcoming = ComplianceEvent::where('status', 'pending')
            ->where('reminder_sent', false)
            ->whereDate('due_date', '>=', now()->toDateString())
            ->whereDate('due_date', '<=', now()->addDays($days)->toDateString())
            ->get();

        foreach ($upcoming as $event) {
            $event->update(['reminder_sent' => true]);
        }

        $this->info("Compliance reminders processed. Overdue: {$overdue->count()}, Upcoming reminders flagged: {$upcoming->count()}.");

        return self::SUCCESS;
    }
}
