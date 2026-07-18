<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\FundDeposit;
use App\Models\Tenant\FundTransaction;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundController extends Controller
{
    /**
     * GET /funds — balance overview + recent transactions.
     */
    public function index(): Response
    {
        $balance = FundBalance::current();

        $recentTransactions = FundTransaction::query()
            ->with('performedBy:id,name')
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'transaction_ref' => $t->transaction_ref,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'is_credit' => $t->type->isCredit(),
                'amount' => (float) $t->amount,
                'balance_after' => (float) $t->balance_after,
                'description' => $t->description,
                'performed_by' => $t->performedBy?->name,
                'created_at' => $t->created_at->format('d M Y H:i'),
            ]);

        $pendingDeposits = FundDeposit::pending()
            ->with('depositedBy:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'amount' => (float) $d->amount,
                'source' => $d->source,
                'deposit_date' => $d->deposit_date->toDateString(),
                'deposited_by' => $d->depositedBy?->name,
            ]);

        return Inertia::render('funds/Index', [
            'balance' => [
                'available_balance' => (float) $balance->available_balance,
                'total_deposits' => (float) $balance->total_deposits,
                'total_disbursed' => (float) $balance->total_disbursed,
                'total_repaid' => (float) $balance->total_repaid,
                'total_expenses' => (float) $balance->total_expenses,
                'currency' => $balance->currency,
            ],
            'recentTransactions' => $recentTransactions,
            'pendingDeposits' => $pendingDeposits,
        ]);
    }

    /**
     * GET /funds/deposits — paginated deposit list.
     */
    public function deposits(Request $request): Response
    {
        $deposits = FundDeposit::query()
            ->with(['depositedBy:id,name', 'approvedBy:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('reference', 'like', "%{$s}%")
                    ->orWhere('source', 'like', "%{$s}%");
            }))
            ->latest('deposit_date')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('funds/Deposits', [
            'deposits' => $deposits->through(fn ($d) => [
                'id' => $d->id,
                'reference' => $d->reference,
                'amount' => (float) $d->amount,
                'source' => $d->source,
                'payment_method' => $d->payment_method,
                'deposit_date' => $d->deposit_date->toDateString(),
                'status' => $d->status,
                'deposited_by' => $d->depositedBy?->name,
                'approved_by' => $d->approvedBy?->name,
                'approved_at' => $d->approved_at?->toDateString(),
            ]),
            'filters' => $request->only('status', 'search'),
        ]);
    }
}
