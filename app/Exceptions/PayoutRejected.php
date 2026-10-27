<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The provider definitively refused a payout (or it could never be sent:
 * missing credentials, no phone, unsupported provider) — no money moved, so
 * the disbursement can safely be reversed.
 *
 * Anything else thrown during a payout (timeouts, 5xx) is ambiguous: the money
 * may have left, so it must be verified by a human, never auto-reversed.
 */
class PayoutRejected extends RuntimeException {}
