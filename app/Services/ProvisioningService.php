<?php

namespace App\Services;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanProvision;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\ProvisionRate;

class ProvisioningService
{
    /**
     * Calculate and persist an ECL provision for a single loan.
     * Returns the LoanProvision record created.
     */
    public function calculateForLoan(Loan $loan, int $recordedBy, ?string $notes = null): LoanProvision
    {
        $dpd = $this->maxDpd($loan);
        $outstanding = (float) $loan->outstanding_balance;
        $rate = ProvisionRate::forDpd($dpd);

        $provisionRate = $rate ? (float) $rate->provision_rate : 0.0;
        $stage = $rate ? $rate->stage : 1;
        $stageLabel = $rate ? $rate->stage_label : 'Stage 1 — Performing';
        $provisionAmount = round($outstanding * ($provisionRate / 100), 2);

        return LoanProvision::create([
            'loan_id' => $loan->id,
            'recorded_by' => $recordedBy,
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'days_past_due' => $dpd,
            'outstanding_balance' => $outstanding,
            'provision_rate' => $provisionRate,
            'provision_amount' => $provisionAmount,
            'calculation_date' => now()->toDateString(),
            'notes' => $notes,
        ]);
    }

    /**
     * Run provisioning across all active/disbursed loans.
     * Returns a summary array.
     */
    public function runPortfolio(int $recordedBy): array
    {
        $loans = Loan::whereIn('status', ['disbursed', 'active'])
            ->with(['schedule' => fn ($q) => $q->where('is_paid', false)->orderBy('due_date')])
            ->get();

        $totalProvision = 0.0;
        $byStage = [1 => 0.0, 2 => 0.0, 3 => 0.0];
        $countByStage = [1 => 0,   2 => 0,   3 => 0];

        foreach ($loans as $loan) {
            $provision = $this->calculateForLoan($loan, $recordedBy);
            $totalProvision += (float) $provision->provision_amount;
            $stage = $provision->stage;

            $byStage[$stage] += (float) $provision->provision_amount;
            $countByStage[$stage] += 1;
        }

        return [
            'loans_assessed' => $loans->count(),
            'total_provision' => round($totalProvision, 2),
            'by_stage' => [
                'stage_1' => ['loans' => $countByStage[1], 'provision' => round($byStage[1], 2)],
                'stage_2' => ['loans' => $countByStage[2], 'provision' => round($byStage[2], 2)],
                'stage_3' => ['loans' => $countByStage[3], 'provision' => round($byStage[3], 2)],
            ],
            'calculation_date' => now()->toDateString(),
        ];
    }

    /**
     * Compute maximum days past due for a loan based on its overdue schedule.
     */
    private function maxDpd(Loan $loan): int
    {
        $oldest = LoanSchedule::where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->where('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->value('due_date');

        if (! $oldest) {
            return 0;
        }

        return (int) \Carbon\Carbon::parse($oldest)->startOfDay()->diffInDays(now()->startOfDay());
    }
}
