<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Disbursed = 'disbursed';
    case Active = 'active';
    case Completed = 'completed';
    case Denied = 'denied';
    case Frozen = 'frozen';
    case Defaulted = 'defaulted';
    case ReLoan = 're_loaned';
    case WrittenOff = 'written_off';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Approved => 'Approved',
            self::Disbursed => 'Disbursed',
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Denied => 'Denied',
            self::Frozen => 'Frozen',
            self::Defaulted => 'Defaulted',
            self::ReLoan => 'Re-loaned',
            self::WrittenOff => 'Written Off',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Submitted => 'info',
            self::Approved => 'warning',
            self::Disbursed => 'primary',
            self::Active => 'success',
            self::Completed => 'success',
            self::Denied => 'danger',
            self::Frozen => 'warning',
            self::Defaulted => 'danger',
            self::ReLoan => 'info',
            self::WrittenOff => 'danger',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Disbursed, self::Active]);
    }

    public function canTransitionTo(self $next): bool
    {
        $allowed = match ($this) {
            self::Draft => [self::Submitted],
            self::Submitted => [self::Approved, self::Denied],
            self::Approved => [self::Disbursed, self::Denied],
            self::Disbursed => [self::Active, self::Frozen, self::Completed],
            self::Active => [self::Completed, self::Frozen, self::Defaulted],
            self::Frozen => [self::Active, self::WrittenOff],
            self::Defaulted => [self::WrittenOff, self::Active],
            default => [],
        };

        return in_array($next, $allowed);
    }
}
