<?php

namespace App\Commands;

use App\Services\InterestAccrualService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AccrueInterestCommand extends Command
{
    protected $signature = 'lendr:accrue-interest
                            {--date= : Accrual date (Y-m-d, default: yesterday)}
                            {--dry-run : Calculate without persisting}';

    protected $description = 'Accrue daily interest for all active loans';

    public function handle(InterestAccrualService $service): int
    {
        $date   = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : now()->subDay();

        $dryRun = (bool) $this->option('dry-run');

        $this->info(sprintf(
            'Accruing interest for %s%s',
            $date->toDateString(),
            $dryRun ? ' [DRY RUN]' : ''
        ));

        $result = $service->accrueForDate($date, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Loans processed', $result['loans_processed']],
                ['Loans suspended',  $result['loans_suspended']],
                ['Already skipped',  $result['skipped']],
                ['Total accrued',    number_format($result['total_accrued'], 2)],
            ]
        );

        return self::SUCCESS;
    }
}
