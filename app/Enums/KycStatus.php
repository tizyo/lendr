<?php

namespace App\Enums;

enum KycStatus: string
{
    case Pending  = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Expired  = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Pending  => 'Pending Review',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::Expired  => 'Expired',
        };
    }
}
