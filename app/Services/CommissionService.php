<?php

namespace App\Services;

use App\Models\Tenant\CommissionRule;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StaffCommission;
use Carbon\Carbon;

class CommissionService
{
    /**
     * Calculate and record commission(s) when a loan is disbursed.
     */
    public function calculateForDisbursement(Loan $loan): array
    {
        if (! $loan->created_by) {
            return [];
        }

        $rules = $this->matchingRules('disbursement', $loan->created_by, $loan->loan_type_id, (float) $loan->principal_amount);
        $created = [];

        foreach ($rules as $rule) {
            $amount = $rule->calculate((float) $loan->principal_amount);
            if ($amount <= 0) {
                continue;
            }

            $created[] = StaffCommission::create([
                'user_id' => $loan->created_by,
                'loan_id' => $loan->id,
                'rule_id' => $rule->id,
                'trigger' => 'disbursement',
                'base_amount' => $loan->principal_amount,
                'commission_amount' => $amount,
                'status' => 'pending',
                'period_month' => now()->startOfMonth()->toDateString(),
            ]);
        }

        return $created;
    }

    /**
     * Calculate and record commission(s) when a payment is received.
     */
    public function calculateForRepayment(Payment $payment): array
    {
        $loan = $payment->loan;
        if (! $loan || ! $loan->created_by) {
            return [];
        }

        $repaymentAmount = (float) $payment->principal_allocated + (float) $payment->interest_allocated;
        $rules = $this->matchingRules('repayment', $loan->created_by, $loan->loan_type_id, $repaymentAmount);
        $created = [];

        foreach ($rules as $rule) {
            $amount = $rule->calculate($repaymentAmount);
            if ($amount <= 0) {
                continue;
            }

            $created[] = StaffCommission::create([
                'user_id' => $loan->created_by,
                'loan_id' => $loan->id,
                'rule_id' => $rule->id,
                'trigger' => 'repayment',
                'base_amount' => $repaymentAmount,
                'commission_amount' => $amount,
                'status' => 'pending',
                'period_month' => now()->startOfMonth()->toDateString(),
            ]);
        }

        return $created;
    }

    /**
     * Approve all pending commissions for a given month (YYYY-MM).
     */
    public function approvePeriod(string $period, int $approvedBy): int
    {
        $from = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $to = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        return StaffCommission::where('status', 'pending')
            ->whereBetween('period_month', [$from, $to])
            ->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => $approvedBy,
            ]);
    }

    /**
     * Mark a set of commission records as paid.
     */
    public function markPaid(array $commissionIds, int $paidBy): int
    {
        return StaffCommission::whereIn('id', $commissionIds)
            ->where('status', 'approved')
            ->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_by' => $paidBy,
            ]);
    }

    /**
     * Summary for a user for a given month (YYYY-MM).
     */
    public function summary(int $userId, string $period): array
    {
        $from = Carbon::createFromFormat('Y-m', $period)->startOfMonth()->toDateString();
        $to = Carbon::createFromFormat('Y-m', $period)->endOfMonth()->toDateString();

        $rows = StaffCommission::where('user_id', $userId)
            ->whereBetween('period_month', [$from, $to])
            ->get();

        return [
            'period' => $period,
            'user_id' => $userId,
            'pending' => ['count' => $rows->where('status', 'pending')->count(),  'amount' => (float) $rows->where('status', 'pending')->sum('commission_amount')],
            'approved' => ['count' => $rows->where('status', 'approved')->count(), 'amount' => (float) $rows->where('status', 'approved')->sum('commission_amount')],
            'paid' => ['count' => $rows->where('status', 'paid')->count(),     'amount' => (float) $rows->where('status', 'paid')->sum('commission_amount')],
            'total' => ['count' => $rows->count(), 'amount' => (float) $rows->sum('commission_amount')],
        ];
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    private function matchingRules(string $trigger, int $userId, ?int $loanTypeId, float $amount): \Illuminate\Database\Eloquent\Collection
    {
        return CommissionRule::where('trigger', $trigger)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', $userId))
            ->where(fn ($q) => $q->whereNull('loan_type_id')->orWhere('loan_type_id', $loanTypeId))
            ->where(fn ($q) => $q->whereNull('min_amount')->orWhere('min_amount', '<=', $amount))
            ->where(fn ($q) => $q->whereNull('max_amount')->orWhere('max_amount', '>=', $amount))
            ->get();
    }
}
