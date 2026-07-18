<?php

namespace App\Services;

use App\Models\Tenant\LoanPenalty;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\User;
use App\Traits\UsesBcMath;
use Carbon\Carbon;

class PenaltyService
{
    use UsesBcMath;

    /**
     * Apply penalties for all overdue schedules on the given date.
     */
    public function applyPenaltiesForDate(Carbon $date, bool $dryRun = false): array
    {
        $schedules = LoanSchedule::where('is_paid', false)
            ->where('due_date', '<', $date->toDateString())
            ->with('loan')
            ->get();

        $totalPenalty = '0';
        $applied = 0;
        $skipped = 0;

        foreach ($schedules as $schedule) {
            if (! $schedule->loan || ! in_array($schedule->loan->status->value, ['active', 'disbursed'])) {
                $skipped++;

                continue;
            }

            // Skip if already penalised for this schedule+date
            $exists = LoanPenalty::where('schedule_id', $schedule->id)
                ->whereDate('penalty_date', $date->toDateString())
                ->exists();

            if ($exists) {
                $skipped++;

                continue;
            }

            $result = $this->applyPenaltyForSchedule($schedule, $date, $dryRun);

            if ($result['penalty_amount'] > 0) {
                $totalPenalty = bcadd($totalPenalty, (string) $result['penalty_amount'], 2);
                $applied++;
            } else {
                $skipped++;
            }
        }

        return [
            'penalty_date' => $date->toDateString(),
            'applied' => $applied,
            'skipped' => $skipped,
            'total_penalty' => (float) $totalPenalty,
            'dry_run' => $dryRun,
        ];
    }

    /**
     * Calculate and optionally persist a penalty for one overdue installment.
     */
    public function applyPenaltyForSchedule(LoanSchedule $schedule, Carbon $date, bool $dryRun = false): array
    {
        $loan = $schedule->loan;
        $penaltyRate = (float) ($loan->penalty_rate ?? 0);          // % per day
        $overdueAmt = (float) $schedule->outstanding;
        $daysOverdue = (int) \Carbon\Carbon::parse($schedule->due_date)->startOfDay()
            ->diffInDays($date->startOfDay());
        $penaltyAmt = (float) $this->bcround(bcmul((string) $overdueAmt, bcdiv((string) $penaltyRate, '100', 10), 10));

        if (! $dryRun && $penaltyAmt > 0) {
            LoanPenalty::create([
                'loan_id' => $loan->id,
                'schedule_id' => $schedule->id,
                'penalty_date' => $date->toDateString(),
                'days_overdue' => $daysOverdue,
                'penalty_rate' => $penaltyRate,
                'overdue_amount' => $overdueAmt,
                'penalty_amount' => $penaltyAmt,
                'status' => 'applied',
            ]);
        }

        return [
            'loan_id' => $loan->id,
            'schedule_id' => $schedule->id,
            'days_overdue' => $daysOverdue,
            'penalty_amount' => $penaltyAmt,
        ];
    }

    /**
     * Waive all or part of a penalty.
     */
    public function waivePenalty(LoanPenalty $penalty, User $user, float $amount, string $reason): LoanPenalty
    {
        $waived = min($amount, (float) $penalty->penalty_amount);

        $penalty->update([
            'waived_amount' => $waived,
            'waived_by' => $user->id,
            'waived_at' => now(),
            'waiver_reason' => $reason,
            'status' => $waived >= (float) $penalty->penalty_amount ? 'waived' : 'applied',
        ]);

        return $penalty->fresh();
    }
}
