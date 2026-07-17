<?php

namespace App\Services;

use App\Models\Tenant\Loan;
use App\Models\Tenant\RiskFlag;
use App\Models\Tenant\RiskPolicy;

class RiskAssessmentService
{
    /**
     * Assess a loan application against all active risk policies.
     * Creates RiskFlag records for each triggered policy.
     * Returns ['result' => 'pass'|'warn'|'block', 'flags' => [...]]
     */
    public function assess(Loan $loan): array
    {
        $borrower = $loan->borrower;

        $context = [
            'borrower_id'      => $borrower->id,
            'credit_score'     => (int) ($borrower->credit_score ?? 0),
            'monthly_income'   => (float) ($borrower->monthly_income ?? 0),
            'requested_amount' => (float) $loan->principal_amount,
            'city'             => strtolower(trim($borrower->city ?? '')),
            'employer'         => strtolower(trim($borrower->employer ?? '')),
            'date_of_birth'    => $borrower->date_of_birth ?? null,
        ];

        // Clear previous unoverridden flags for this loan
        RiskFlag::where('loan_id', $loan->id)
            ->where('overridden', false)
            ->delete();

        $policies = RiskPolicy::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $flags = [];

        foreach ($policies as $policy) {
            $detail = $policy->evaluate($context);

            if ($detail === null) {
                continue;
            }

            $flag = RiskFlag::create([
                'loan_id'        => $loan->id,
                'risk_policy_id' => $policy->id,
                'severity'       => $policy->action,
                'detail'         => $detail,
                'overridden'     => false,
            ]);

            $flags[] = [
                'id'       => $flag->id,
                'policy'   => $policy->name,
                'severity' => $policy->action,
                'detail'   => $detail,
            ];
        }

        $result = $this->determineResult($flags);

        return compact('result', 'flags');
    }

    private function determineResult(array $flags): string
    {
        if (empty($flags)) {
            return 'pass';
        }

        foreach ($flags as $flag) {
            if ($flag['severity'] === 'block') {
                return 'block';
            }
        }

        return 'warn';
    }
}
