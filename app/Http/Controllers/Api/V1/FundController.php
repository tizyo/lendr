<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FundController extends BaseApiController
{
    /**
     * GET /api/v1/funds/balance
     * Current fund balance and component breakdown.
     */
    public function balance(): JsonResponse
    {
        $balance = FundBalance::current();

        return $this->success([
            'available_balance' => (float) $balance->available_balance,
            'opening_balance' => (float) $balance->opening_balance,
            'total_deposits' => (float) $balance->total_deposits,
            'total_disbursed' => (float) $balance->total_disbursed,
            'total_repaid' => (float) $balance->total_repaid,
            'total_penalties' => (float) $balance->total_penalties,
            'total_expenses' => (float) $balance->total_expenses,
            'currency' => $balance->currency,
            'last_reconciled_at' => $balance->last_reconciled_at?->toDateTimeString(),
        ]);
    }

    /**
     * GET /api/v1/funds/summary
     * Summary stats including utilization rate.
     */
    public function summary(): JsonResponse
    {
        $balance = FundBalance::current();

        $totalIn = (float) $balance->total_deposits + (float) $balance->total_repaid + (float) $balance->total_penalties;
        $totalOut = (float) $balance->total_disbursed + (float) $balance->total_expenses;

        $utilization = $totalIn > 0
            ? round(($totalOut / $totalIn) * 100, 2)
            : 0;

        return $this->success([
            'available_balance' => (float) $balance->available_balance,
            'total_in' => $totalIn,
            'total_out' => $totalOut,
            'utilization_rate' => $utilization,
            'currency' => $balance->currency,
        ]);
    }

    /**
     * GET /api/v1/funds/transactions
     * Paginated immutable ledger.
     */
    public function transactions(Request $request): JsonResponse
    {
        $transactions = FundTransaction::query()
            ->with('performedBy:id,name')
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->latest()
            ->paginate(25);

        return $this->paginated($transactions, fn ($t) => $this->formatTransaction($t));
    }

    private function formatTransaction(FundTransaction $t): array
    {
        return [
            'id' => $t->id,
            'transaction_ref' => $t->transaction_ref,
            'type' => $t->type->value,
            'type_label' => $t->type->label(),
            'is_credit' => $t->type->isCredit(),
            'amount' => (float) $t->amount,
            'balance_before' => (float) $t->balance_before,
            'balance_after' => (float) $t->balance_after,
            'description' => $t->description,
            'performed_by' => $t->relationLoaded('performedBy') ? $t->performedBy?->name : null,
            'created_at' => $t->created_at->format('d M Y H:i'),
        ];
    }
}
