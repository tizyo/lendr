<?php

namespace App\Enums;

enum KycStatus: string
{
    case Pending     = 'pending';
    case UnderReview = 'under_review';
    case Verified    = 'verified';
    case Rejected    = 'rejected';
    case Expired     = 'expired';

    public function label(): string
    {
        return match($this) {
            self::Pending     => 'Pending Review',
            self::UnderReview => 'Under Review',
            self::Verified    => 'Verified',
            self::Rejected    => 'Rejected',
            self::Expired     => 'Expired',
        };
    }

    /** Returns allowed next statuses for the state machine. */
    public function transitions(): array
    {
        return match($this) {
            self::Pending     => [self::UnderReview],
            self::UnderReview => [self::Verified, self::Rejected],
            self::Verified    => [],
            self::Rejected    => [self::Pending],
            self::Expired     => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->transitions(), strict: true);
    }
}
