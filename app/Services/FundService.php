<?php

namespace App\Services;

use App\Enums\FundTransactionType;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundDeposit;
use App\Models\Tenant\FundTransaction;
use App\Traits\UsesBcMath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundService
{
    use UsesBcMath;

    /**
     * Record a capital deposit being approved — credits available_balance.
     */
    public function recordDeposit(FundDeposit $deposit, int $userId): FundTransaction
    {
        return DB::transaction(function () use ($deposit, $userId) {
            return $this->credit(
                (float) $deposit->amount,
                FundTransactionType::Deposit,
                $deposit,
                $userId,
                "Capital deposit: {$deposit->reference} from {$deposit->source}",
            );
        });
    }

    /**
     * Debit fund for a loan disbursement.
     */
    public function recordDisbursement(Model $loan, int $userId): FundTransaction
    {
        return DB::transaction(function () use ($loan, $userId) {
            return $this->debit(
                (float) $loan->principal_amount,
                FundTransactionType::Disburse,
                $loan,
                $userId,
                "Loan disbursement: {$loan->loan_number}",
            );
        });
    }

    /**
     * Credit fund for a loan repayment.
     */
    public function recordRepayment(Model $payment, float $amount, ?int $userId): FundTransaction
    {
        return DB::transaction(function () use ($payment, $amount, $userId) {
            return $this->credit(
                $amount,
                FundTransactionType::Repayment,
                $payment,
                $userId,
                "Loan repayment: {$payment->receipt_number}",
            );
        });
    }

    /**
     * Credit fund for penalty collected.
     */
    public function recordPenalty(Model $payment, float $amount, ?int $userId): FundTransaction
    {
        return DB::transaction(function () use ($payment, $amount, $userId) {
            return $this->credit(
                $amount,
                FundTransactionType::Penalty,
                $payment,
                $userId,
                "Penalty collected: {$payment->receipt_number}",
            );
        });
    }

    /**
     * Debit fund for an expense.
     */
    public function recordExpense(Model $expense, float $amount, int $userId): FundTransaction
    {
        return DB::transaction(function () use ($expense, $amount, $userId) {
            return $this->debit(
                $amount,
                FundTransactionType::Withdrawal,
                $expense,
                $userId,
                "Expense: #{$expense->id}",
            );
        });
    }

    /**
     * Reverse a deposit (when reversal is needed).
     */
    public function reverseDeposit(FundDeposit $deposit, int $userId): FundTransaction
    {
        return DB::transaction(function () use ($deposit, $userId) {
            return $this->debit(
                (float) $deposit->amount,
                FundTransactionType::Adjustment,
                $deposit,
                $userId,
                "Deposit reversal: {$deposit->reference}",
            );
        });
    }

    // ─── Low-level ledger writers ─────────────────────────────────────────────

    public function credit(float $amount, FundTransactionType $type, Model $source, ?int $userId, ?string $description = null): FundTransaction
    {
        $balance = FundBalance::lockForUpdate()->first() ?? FundBalance::current();

        $before = (float) $balance->available_balance;
        $after = (float) $this->bcround(bcadd((string) $before, (string) $amount, 10));

        $this->updateBalanceForCredit($balance, $type, $amount, $after);

        return FundTransaction::create([
            'transaction_ref' => $this->generateRef(),
            'type' => $type->value,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'performed_by' => $userId,
            'description' => $description,
        ]);
    }

    public function debit(float $amount, FundTransactionType $type, Model $source, ?int $userId, ?string $description = null): FundTransaction
    {
        $balance = FundBalance::lockForUpdate()->first() ?? FundBalance::current();

        $before = (float) $balance->available_balance;
        $after = (float) $this->bcround(bcsub((string) $before, (string) $amount, 10));

        $this->updateBalanceForDebit($balance, $type, $amount, $after);

        return FundTransaction::create([
            'transaction_ref' => $this->generateRef(),
            'type' => $type->value,
            'amount' => $amount,
            'balance_before' => $before,
            'balance_after' => $after,
            'source_type' => $source->getMorphClass(),
            'source_id' => $source->getKey(),
            'performed_by' => $userId,
            'description' => $description,
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function updateBalanceForCredit(FundBalance $balance, FundTransactionType $type, float $amount, float $newAvailable): void
    {
        $fields = ['available_balance' => $newAvailable];

        $fields += match ($type) {
            FundTransactionType::Deposit => ['total_deposits' => (float) $this->bcround(bcadd((string) $balance->total_deposits, (string) $amount, 10))],
            FundTransactionType::Repayment => ['total_repaid' => (float) $this->bcround(bcadd((string) $balance->total_repaid, (string) $amount, 10))],
            FundTransactionType::Penalty => ['total_penalties' => (float) $this->bcround(bcadd((string) $balance->total_penalties, (string) $amount, 10))],
            default => [],
        };

        $balance->update($fields);
    }

    private function updateBalanceForDebit(FundBalance $balance, FundTransactionType $type, float $amount, float $newAvailable): void
    {
        $fields = ['available_balance' => $newAvailable];

        $fields += match ($type) {
            FundTransactionType::Disburse => ['total_disbursed' => (float) $this->bcround(bcadd((string) $balance->total_disbursed, (string) $amount, 10))],
            FundTransactionType::Withdrawal => ['total_expenses' => (float) $this->bcround(bcadd((string) $balance->total_expenses, (string) $amount, 10))],
            default => [],
        };

        $balance->update($fields);
    }

    private function generateRef(): string
    {
        $prefix = 'TXN-'.now()->format('Ym').'-';
        $last = FundTransaction::where('transaction_ref', 'like', $prefix.'%')->max('transaction_ref');
        $seq = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
}
