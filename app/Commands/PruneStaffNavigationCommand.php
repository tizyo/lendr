<?php

namespace App\Commands;

use App\Commands\Concerns\RunsForEachTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Screen visits (TrackStaffActivity) are only useful for recent history; the
 * workspace's real audit trail (changes, sign-ins) is left alone.
 */
class PruneStaffNavigationCommand extends Command
{
    use RunsForEachTenant;

    protected $signature = 'lendr:prune-navigation {--days=90 : Keep this many days of screen visits}';

    protected $description = 'Delete staff screen-visit entries older than the given number of days';

    public function __construct()
    {
        parent::__construct();

        // Every workspace, including paused ones: their old visits go too.
        $this->tenantStatuses = ['active', 'trial', 'expired', 'suspended', 'cancelled'];
    }

    public function handle(): int
    {
        if (! Schema::hasTable('activity_log')) {
            return self::SUCCESS;
        }

        $deleted = DB::table('activity_log')
            ->where('log_name', 'navigation')
            ->where('created_at', '<', now()->subDays(max(1, (int) $this->option('days'))))
            ->delete();

        $this->info("Deleted {$deleted} screen visit(s).");

        return self::SUCCESS;
    }
}
