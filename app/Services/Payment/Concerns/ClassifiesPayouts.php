<?php

namespace App\Services\Payment\Concerns;

use App\Exceptions\PayoutRejected;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;

/**
 * Shared rules for deciding whether a payout attempt definitely failed
 * (PayoutRejected — safe to reverse) or ended in an unknown state (any other
 * exception — a human must check the provider before anything is undone).
 *
 * Several providers answer HTTP 200 while refusing the payout, so "accepted"
 * is judged from the body, never from the status code alone.
 */
trait ClassifiesPayouts
{
    /** Before the payout request is sent nothing can have moved, so any failure is definitive. */
    protected function beforeSending(callable $step): mixed
    {
        try {
            return $step();
        } catch (PayoutRejected $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new PayoutRejected($e->getMessage(), 0, $e);
        }
    }

    /** Non-2xx payout response: 4xx = refused, 5xx = unknown (it may have gone through). */
    protected function providerError(string $provider, Response $response): never
    {
        $message = "{$provider} payout failed ({$response->status()}): ".Str::limit($response->body(), 300);

        throw $response->serverError() ? new \RuntimeException($message) : new PayoutRejected($message);
    }

    /** PawaPay answers 200 with status REJECTED when it refuses a payout. */
    protected function assertPawaPayAccepted(Response $response): void
    {
        if (! $response->successful()) {
            $this->providerError('PawaPay', $response);
        }

        $status = strtoupper((string) $response->json('status'));

        if (in_array($status, ['ACCEPTED', 'ENQUEUED', 'DUPLICATE_IGNORED'], true)) {
            return;
        }

        $reason = $response->json('rejectionReason.rejectionMessage') ?? $response->json('rejectionReason.rejectionCode') ?? $status;

        throw new PayoutRejected("PawaPay rejected the payout: {$reason}");
    }

    /** Airtel answers 200 with status.success = false when it refuses a payout. */
    protected function assertAirtelAccepted(Response $response): void
    {
        if (! $response->successful()) {
            $this->providerError('Airtel', $response);
        }

        if ($response->json('status.success') === true) {
            return;
        }

        $message = (string) ($response->json('status.message') ?? 'unknown error');
        $detail = "Airtel payout not successful ({$response->json('status.response_code')}): {$message}";

        // Airtel flags some outcomes as ambiguous / in progress — those must be checked, not reversed.
        throw preg_match('/ambiguous|progress|pending/i', $message)
            ? new \RuntimeException($detail)
            : new PayoutRejected($detail);
    }

    /** Flutterwave answers 200 with status "error" when it refuses a transfer. */
    protected function assertFlutterwaveAccepted(Response $response): void
    {
        if (! $response->successful()) {
            $this->providerError('Flutterwave', $response);
        }

        if ($response->json('status') !== 'success') {
            throw new PayoutRejected('Flutterwave refused the transfer: '.($response->json('message') ?? 'unknown error'));
        }
    }
}
