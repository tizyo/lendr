<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\StandingOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Tenant API — view and manage standing orders for a loan.
 *
 * GET   /api/v1/loans/{loan}/standing-orders          — list
 * PATCH /api/v1/standing-orders/{order}/cancel        — cancel a pending order
 */
class StandingOrderController extends BaseApiController
{
    public function index(Loan $loan): JsonResponse
    {
        $orders = $loan->standingOrders()
            ->with('payment:id,receipt_number,amount,payment_date')
            ->get();

        return $this->success($orders->map(fn ($o) => $this->format($o)));
    }

    public function cancel(Request $request, StandingOrder $order): JsonResponse
    {
        if (! in_array($order->status, ['pending', 'failed'])) {
            return $this->error('Only pending or failed orders can be cancelled.', 422);
        }

        $order->update(['status' => 'cancelled']);

        return $this->success($this->format($order), 'Standing order cancelled.');
    }

    private function format(StandingOrder $o): array
    {
        return [
            'id' => $o->id,
            'loan_schedule_id' => $o->loan_schedule_id,
            'due_date' => $o->due_date?->format('d M Y'),
            'amount' => (float) $o->amount,
            'phone' => $o->phone,
            'gateway' => $o->gateway,
            'status' => $o->status,
            'retry_count' => $o->retry_count,
            'max_retries' => $o->max_retries,
            'next_attempt_at' => $o->next_attempt_at?->format('d M Y H:i'),
            'processed_at' => $o->processed_at?->format('d M Y H:i'),
            'failure_reason' => $o->failure_reason,
            'provider_reference' => $o->provider_reference,
            'payment' => $o->payment ? [
                'receipt_number' => $o->payment->receipt_number,
                'amount' => (float) $o->payment->amount,
                'payment_date' => $o->payment->payment_date,
            ] : null,
        ];
    }
}
