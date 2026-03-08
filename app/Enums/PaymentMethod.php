<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash         = 'cash';
    case BankTransfer = 'bank_transfer';
    case AirtelMoney  = 'airtel_money';
    case MtnMomo      = 'mtn_momo';
    case ZamtelKwacha = 'zamtel_kwacha';
    case Cheque       = 'cheque';

    public function label(): string
    {
        return match($this) {
            self::Cash         => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::AirtelMoney  => 'Airtel Money',
            self::MtnMomo      => 'MTN MoMo',
            self::ZamtelKwacha => 'Zamtel Kwacha',
            self::Cheque       => 'Cheque',
        };
    }

    public function isMobileMoney(): bool
    {
        return in_array($this, [
            self::AirtelMoney,
            self::MtnMomo,
            self::ZamtelKwacha,
        ]);
    }
}
