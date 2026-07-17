<?php

namespace App\Commands;

use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ApplyPenaltiesCommand extends Command
{
    protected $signature = 'lendr:apply-penalties
                            {--date= : Penalty date (Y-m-d, default: today)}
                            {--dry-run : Calculate without persisting}';

    protected $description = 'Apply daily penalties to all overdue loan installments';

    public function handle(PenaltyService $service): int
    {
        $date   = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now();

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Applying penalties for %s%s',
            $date->toDateString(),
            $dryRun ? ' [DRY RUN]' : ''
        ));

        $result = $service->applyPenaltiesForDate($date, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Installments penalised', $result['applied']],
                ['Skipped',               $result['skipped']],
                ['Total penalty',         number_format($result['total_penalty'], 2)],
            ]
        );

        return self::SUCCESS;
    }
}
