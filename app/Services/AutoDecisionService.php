<?php

namespace App\Services;

use App\Models\Tenant\AutoDecision;
use App\Models\Tenant\AutoDecisionRule;
use App\Models\Tenant\Loan;

class AutoDecisionService
{
    /**
     * Run the auto-decision engine for a loan application.
     * Returns the persisted AutoDecision record.
     */
    public function evaluate(Loan $loan): AutoDecision
    {
        $borrower = $loan->borrower;
        $creditScore = (float) ($borrower->credit_score ?? 0);
        $principal = (float) $loan->principal_amount;

        // Simplified DTI: we don't store income, so treat DTI as null
        $dti = null;

        $factors = [];

        // Load active rules sorted by priority (lower = higher priority)
        $rules = AutoDecisionRule::where('is_active', true)
            ->orderBy('priority')
            ->get();

        $matchedRule = null;
        $decidedAction = 'manual'; // fallback

        foreach ($rules as $rule) {
            if (! $this->ruleMatches($rule, $loan, $creditScore, $dti)) {
                continue;
            }

            $matchedRule = $rule;
            $decidedAction = $rule->action;
            break;
        }

        // Build factors list for transparency
        if ($creditScore > 0) {
            $factors[] = "credit_score:{$creditScore}";
        }
        if ($principal > 0) {
            $factors[] = "principal:{$principal}";
        }
        if ($matchedRule) {
            $factors[] = "matched_rule:{$matchedRule->name}";
        } else {
            $factors[] = 'no_rule_matched';
        }

        return AutoDecision::create([
            'loan_id' => $loan->id,
            'rule_id' => $matchedRule?->id,
            'action' => $decidedAction,
            'credit_score' => $creditScore > 0 ? $creditScore : null,
            'dti_pct' => $dti,
            'factors' => $factors,
        ]);
    }

    private function ruleMatches(AutoDecisionRule $rule, Loan $loan, float $creditScore, ?float $dti): bool
    {
        // Credit score check
        if ($creditScore < (float) $rule->min_credit_score) {
            return false;
        }

        // Principal amount check
        if ($rule->max_loan_amount !== null && (float) $loan->principal_amount > (float) $rule->max_loan_amount) {
            return false;
        }

        // Tenure checks (tenure in months)
        $tenureMonths = (int) $loan->tenure;
        if ($rule->min_tenure_months !== null && $tenureMonths < $rule->min_tenure_months) {
            return false;
        }
        if ($rule->max_tenure_months !== null && $tenureMonths > $rule->max_tenure_months) {
            return false;
        }

        // DTI check
        if ($rule->max_dti_pct !== null && $dti !== null && $dti > (float) $rule->max_dti_pct) {
            return false;
        }

        return true;
    }
}
