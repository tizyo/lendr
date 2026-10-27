<?php

namespace App\Commands;

use App\Commands\Concerns\RunsForEachTenant;
use App\Services\CampaignService;
use Illuminate\Console\Command;

class ProcessCampaignsCommand extends Command
{
    use RunsForEachTenant;

    protected $signature = 'lendr:process-campaigns';

    protected $description = 'Dispatch all scheduled campaigns that are due.';

    public function handle(CampaignService $service): int
    {
        $count = $service->processScheduled();
        $this->info("Processed {$count} scheduled campaign(s).");

        return 0;
    }
}
