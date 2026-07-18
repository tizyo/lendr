<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Models\Tenant\CollectionCase;
use App\Models\Tenant\EscalationRule;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;

class CollectionAutomationService
{
    /**
     * Calculate DPD (days past due) for a loan.
     */
    public function getDpd(Loan $loan): int
    {
        $oldest = LoanSchedule::where('loan_id', $loan->id)
            ->where('is_paid', false)
            ->whereDate('due_date', '<', now()->toDateString())
            ->orderBy('due_date')
            ->value('due_date');

        if (! $oldest) {
            return 0;
        }

        return (int) $oldest->diffInDays(now());
    }

    /**
     * Run escalation check for a single loan.
     * Returns the action taken, or null if no rule matched.
     */
    public function escalate(Loan $loan): ?string
    {
        $statusValue = $loan->status instanceof LoanStatus ? $loan->status->value : $loan->status;
        if (! in_array($statusValue, [LoanStatus::Active->value, LoanStatus::Defaulted->value])) {
            return null;
        }

        $dpd = $this->getDpd($loan);
        if ($dpd === 0) {
            return null;
        }

        $rule = EscalationRule::where('is_active', true)
            ->where('dpd_threshold', '<=', $dpd)
            ->orderByDesc('dpd_threshold')
            ->first();

        if (! $rule) {
            return null;
        }

        // Check if an open/escalated case already exists for this loan+rule
        $existing = CollectionCase::where('loan_id', $loan->id)
            ->whereIn('status', ['open', 'promised', 'escalated'])
            ->first();

        if ($existing) {
            // Escalate further if the rule is more severe
            if ($existing->escalation_rule_id !== $rule->id) {
                $existing->update([
                    'escalation_rule_id' => $rule->id,
                    'action_taken' => $rule->action,
                    'assigned_to' => $rule->assigned_to ?? $existing->assigned_to,
                    'status' => 'escalated',
                ]);
            }

            return $rule->action;
        }

        CollectionCase::create([
            'loan_id' => $loan->id,
            'borrower_id' => $loan->borrower_id,
            'assigned_to' => $rule->assigned_to,
            'escalation_rule_id' => $rule->id,
            'action_taken' => $rule->action,
            'status' => 'open',
            'dpd_at_creation' => $dpd,
        ]);

        return $rule->action;
    }

    /**
     * Run escalation for all overdue loans.
     */
    public function runForAll(): int
    {
        $loans = Loan::whereIn('status', [LoanStatus::Active->value, LoanStatus::Defaulted->value])
            ->get();
        $escalated = 0;

        foreach ($loans as $loan) {
            if ($this->escalate($loan) !== null) {
                $escalated++;
            }
        }

        return $escalated;
    }

    /**
     * Evaluate promise-to-pay statuses (broken if promise_date has passed and loan still overdue).
     */
    public function evaluatePromises(): int
    {
        $broken = 0;

        $overdue = \App\Models\Tenant\PromiseToPay::where('status', 'pending')
            ->whereDate('promise_date', '<', now()->toDateString())
            ->with('loan')
            ->get();

        foreach ($overdue as $promise) {
            // If loan is still active/overdue (not paid), mark promise as broken
            $loanStatus = $promise->loan->status instanceof LoanStatus ? $promise->loan->status->value : $promise->loan->status;
            if (in_array($loanStatus, [LoanStatus::Active->value, LoanStatus::Defaulted->value])) {
                $promise->update(['status' => 'broken']);
                $broken++;
            } else {
                $promise->update(['status' => 'kept']);
            }
        }

        return $broken;
    }
}
