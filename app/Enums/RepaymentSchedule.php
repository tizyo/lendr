<?php

namespace App\Enums;

enum RepaymentSchedule: string
{
    case Daily    = 'daily';
    case Weekly   = 'weekly';
    case BiWeekly = 'bi_weekly';
    case Monthly  = 'monthly';
    case Bullet   = 'bullet'; // lump sum at end

    public function label(): string
    {
        return match($this) {
            self::Daily    => 'Daily',
            self::Weekly   => 'Weekly',
            self::BiWeekly => 'Bi-Weekly',
            self::Monthly  => 'Monthly',
            self::Bullet   => 'Bullet (Lump Sum)',
        };
    }

    public function daysPerPeriod(): int
    {
        return match($this) {
            self::Daily    => 1,
            self::Weekly   => 7,
            self::BiWeekly => 14,
            self::Monthly  => 30,
            self::Bullet   => 0,
        };
    }
}
