<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $payments = Payment::query()
            ->with(['loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name,borrower_number', 'recordedBy:id,name'])
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('receipt_number', 'like', "%{$s}%")
                    ->orWhereHas('loan', fn ($lq) => $lq->where('loan_number', 'like', "%{$s}%"))
                    ->orWhereHas('loan.borrower', fn ($bq) => $bq->where('first_name', 'like', "%{$s}%")
                        ->orWhere('last_name', 'like', "%{$s}%"),
                    );
            }))
            ->when($request->date_from, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->when($request->payment_method, fn ($q, $m) => $q->where('payment_method', $m))
            ->latest('payment_date')
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($p) => [
                'id' => $p->id,
                'receipt_number' => $p->receipt_number,
                'loan_number' => $p->loan->loan_number,
                'borrower_name' => $p->loan->borrower->full_name,
                'borrower_number' => $p->loan->borrower->borrower_number,
                'amount' => number_format((float) $p->amount, 2),
                'payment_method' => $p->payment_method->label(),
                'payment_date' => $p->payment_date->format('d M Y'),
                'reference' => $p->reference,
                'recorded_by' => $p->recordedBy?->name,
            ]);

        return Inertia::render('payments/Index', [
            'payments' => $payments,
            'filters' => $request->only(['search', 'date_from', 'date_to', 'payment_method']),
        ]);
    }

    public function show(Payment $payment): Response
    {
        $payment->load(['loan.borrower', 'loan.loanType:id,name', 'recordedBy:id,name']);

        return Inertia::render('payments/Show', [
            'payment' => [
                'id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'loan_number' => $payment->loan->loan_number,
                'loan_id' => $payment->loan_id,
                'borrower_name' => $payment->loan->borrower->full_name,
                'borrower_number' => $payment->loan->borrower->borrower_number,
                'loan_type' => $payment->loan->loanType->name,
                'amount' => number_format((float) $payment->amount, 2),
                'principal_allocated' => number_format((float) $payment->principal_allocated, 2),
                'interest_allocated' => number_format((float) $payment->interest_allocated, 2),
                'penalty_allocated' => number_format((float) $payment->penalty_allocated, 2),
                'payment_method' => $payment->payment_method->label(),
                'payment_date' => $payment->payment_date->format('d M Y'),
                'reference' => $payment->reference,
                'notes' => $payment->notes,
                'source' => $payment->source,
                'recorded_by' => $payment->recordedBy?->name,
                'created_at' => $payment->created_at->format('d M Y H:i'),
            ],
        ]);
    }
}
