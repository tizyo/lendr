<?php

namespace App\Commands;

use App\Services\CollectionAutomationService;
use Illuminate\Console\Command;

class EscalateCollectionsCommand extends Command
{
    protected $signature   = 'lendr:escalate-collections';
    protected $description = 'Run automated collection escalation based on DPD rules and evaluate promise-to-pay statuses.';

    public function handle(CollectionAutomationService $service): int
    {
        $escalated = $service->runForAll();
        $broken    = $service->evaluatePromises();

        $this->info("Escalation complete: {$escalated} loan(s) escalated, {$broken} promise(s) marked broken.");
        return self::SUCCESS;
    }
}
