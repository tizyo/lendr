<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A loan action (approve, disburse, write off…) that can't go ahead, with a
 * message staff can act on. Carries the HTTP status and any extra payload
 * (e.g. the CRB summary) for API callers.
 */
class LoanActionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 422,
        public readonly string $reason = 'invalid',
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public static function invalid(string $message): self
    {
        return new self($message, 422, 'invalid');
    }

    public static function approvalRequired(string $message): self
    {
        return new self($message, 422, 'approval_required');
    }

    public static function crbAlert(array $crb): self
    {
        return new self(
            'CRB alert: This borrower has active loan(s) at another lender. Pass crb_override=true to proceed.',
            409,
            'crb_alert',
            ['crb' => $crb],
        );
    }
}
