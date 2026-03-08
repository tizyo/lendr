<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin    = 'super_admin';
    case BranchManager = 'branch_manager';
    case LoanOfficer   = 'loan_officer';
    case Cashier       = 'cashier';
    case Accountant    = 'accountant';
    case Auditor       = 'auditor';

    public function label(): string
    {
        return match($this) {
            self::SuperAdmin    => 'Super Admin',
            self::BranchManager => 'Branch Manager',
            self::LoanOfficer   => 'Loan Officer',
            self::Cashier       => 'Cashier',
            self::Accountant    => 'Accountant',
            self::Auditor       => 'Auditor',
        };
    }

    public static function allValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}
