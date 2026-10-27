<?php

namespace App\Enums;

enum FundTransactionType: string
{
    case Deposit = 'deposit';
    case Disburse = 'disburse';
    case Repayment = 'repayment';
    case Penalty = 'penalty';
    case Adjustment = 'adjustment';
    case Withdrawal = 'withdrawal';
    case DisburseReversal = 'disburse_reversal'; // payout failed — funds returned

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Deposit',
            self::Disburse => 'Disbursement',
            self::Repayment => 'Repayment',
            self::Penalty => 'Penalty',
            self::Adjustment => 'Adjustment',
            self::Withdrawal => 'Withdrawal',
            self::DisburseReversal => 'Disbursement reversal',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Deposit, self::Repayment, self::Penalty, self::DisburseReversal]);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::Disburse, self::Withdrawal]);
    }
}
