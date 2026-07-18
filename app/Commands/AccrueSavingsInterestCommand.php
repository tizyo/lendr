<?php

namespace App\Commands;

use App\Models\Tenant\SavingsAccount;
use Illuminate\Console\Command;

class AccrueSavingsInterestCommand extends Command
{
    protected $signature = 'lendr:accrue-savings {--dry-run : Show what would be accrued without saving}';

    protected $description = 'Accrue monthly interest on active savings accounts (fixed & target types with interest_rate > 0)';

    public function handle(): int
    {
        $isDry = $this->option('dry-run');

        $accounts = SavingsAccount::where('status', 'active')
            ->where('interest_rate', '>', 0)
            ->where('balance', '>', 0)
            ->get();

        $processed = 0;
        $skipped = 0;

        foreach ($accounts as $account) {
            if ($isDry) {
                $interest = round(
                    (float) $account->balance * ((float) $account->interest_rate / 100) / 12,
                    2,
                );
                if ($interest > 0) {
                    $this->line("  [dry] Account #{$account->account_number}: +{$interest}");
                    $processed++;
                } else {
                    $skipped++;
                }

                continue;
            }

            $txn = $account->accrueInterest();

            if ($txn) {
                $processed++;
            } else {
                $skipped++;
            }
        }

        $this->info("Savings interest accrual complete. Processed: {$processed}, Skipped: {$skipped}.");

        return self::SUCCESS;
    }
}
