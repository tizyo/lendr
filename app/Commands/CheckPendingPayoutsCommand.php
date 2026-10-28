<?php

namespace App\Commands;

use App\Commands\Concerns\RunsForEachTenant;
use App\Models\Tenant\DisbursementLog;
use App\Services\Payment\DisbursementOutcomeService;
use App\Services\Payment\PayoutStatusChecker;
use Illuminate\Console\Command;

/**
 * Follows up mobile-money payouts the provider accepted but hasn't finished,
 * by asking the provider for the final status (no webhooks needed):
 *
 *   completed → marked completed
 *   failed    → disbursement reversed, staff + borrower told
 *   still pending / can't be checked after --stale-hours → handed to staff
 *               to verify (status "unconfirmed", same as a timed-out payout)
 */
class CheckPendingPayoutsCommand extends Command
{
    use RunsForEachTenant;

    protected $signature = 'lendr:check-payouts
        {--stale-hours=48 : Hand a payout to staff if it is still unresolved after this long}';

    protected $description = 'Check accepted mobile-money payouts with the provider and settle their final outcome.';

    /** Give providers a moment before the first look. */
    private const MIN_AGE_MINUTES = 5;

    public function handle(PayoutStatusChecker $checker, DisbursementOutcomeService $outcome): int
    {
        $staleHours = (int) $this->option('stale-hours');
        $counts = ['completed' => 0, 'failed' => 0, 'pending' => 0, 'unknown' => 0, 'escalated' => 0];

        DisbursementLog::where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes(self::MIN_AGE_MINUTES))
            ->with('loan')
            ->orderBy('id')
            ->each(function (DisbursementLog $log) use ($checker, $outcome, $staleHours, &$counts) {
                if (! $log->loan) {
                    return;
                }

                ['state' => $state, 'reason' => $reason] = $checker->check($log);
                $counts[$state]++;

                match ($state) {
                    'completed' => $outcome->completed($log),
                    'failed' => $outcome->failedAfterAcceptance($log->loan, $log, $reason ?: 'reported as failed by the provider'),
                    default => null,
                };

                if (in_array($state, ['pending', 'unknown'], true) && $log->created_at->lte(now()->subHours($staleHours))) {
                    $outcome->unconfirmed($log->loan, $log, "Still not confirmed after {$staleHours} hours".($reason ? " ({$reason})" : ''));
                    $counts['escalated']++;
                }
            });

        $this->info(sprintf(
            'Payouts — completed: %d, failed: %d, still pending: %d, uncheckable: %d, handed to staff: %d.',
            $counts['completed'], $counts['failed'], $counts['pending'], $counts['unknown'], $counts['escalated'],
        ));

        return self::SUCCESS;
    }
}
