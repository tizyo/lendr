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

    /**
     * Relative privilege level, highest first. Used to stop a staff member
     * from granting a role above their own (see StaffController).
     */
    public function level(): int
    {
        return match ($this) {
            self::SuperAdmin    => 100,
            self::BranchManager => 80,
            self::Accountant    => 60,
            self::Auditor       => 50,
            self::LoanOfficer   => 40,
            self::Cashier       => 30,
        };
    }
}
