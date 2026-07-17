<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExpenseController extends Controller
{
    /**
     * GET /expenses — paginated list with filters.
     */
    public function index(Request $request): Response
    {
        $expenses = Expense::query()
            ->with(['category:id,name,code,colour', 'submittedBy:id,name', 'approvedBy:id,name'])
            ->when($request->status,      fn ($q, $s) => $q->where('status', $s))
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('expense_number', 'like', "%{$s}%")
                  ->orWhere('title', 'like', "%{$s}%")
                  ->orWhere('vendor', 'like', "%{$s}%");
            }))
            ->when($request->date_from, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->latest('expense_date')
            ->paginate(20)
            ->withQueryString();

        $categories = ExpenseCategory::active()->orderBy('name')->get(['id', 'name', 'colour']);

        return Inertia::render('expenses/Index', [
            'expenses'   => $expenses->through(fn ($e) => [
                'id'             => $e->id,
                'expense_number' => $e->expense_number,
                'title'          => $e->title,
                'amount'         => (float) $e->amount,
                'currency'       => $e->currency,
                'expense_date'   => $e->expense_date?->toDateString(),
                'status'         => $e->status->value,
                'vendor'         => $e->vendor,
                'category'       => $e->category ? ['id' => $e->category->id, 'name' => $e->category->name, 'colour' => $e->category->colour] : null,
                'submitted_by'   => $e->submittedBy?->name,
                'approved_by'    => $e->approvedBy?->name,
            ]),
            'categories' => $categories,
            'filters'    => $request->only('status', 'category_id', 'search', 'date_from', 'date_to'),
        ]);
    }

    /**
     * GET /expenses/{expense} — full detail view.
     */
    public function show(Expense $expense): Response
    {
        $expense->load(['category', 'submittedBy:id,name', 'approvedBy:id,name', 'documents']);

        return Inertia::render('expenses/Show', [
            'expense' => [
                'id'               => $expense->id,
                'expense_number'   => $expense->expense_number,
                'title'            => $expense->title,
                'description'      => $expense->description,
                'amount'           => (float) $expense->amount,
                'currency'         => $expense->currency,
                'expense_date'     => $expense->expense_date?->toDateString(),
                'status'           => $expense->status->value,
                'payment_method'   => $expense->payment_method,
                'vendor'           => $expense->vendor,
                'receipt_reference' => $expense->receipt_reference,
                'rejection_reason' => $expense->rejection_reason,
                'category'         => $expense->category ? [
                    'id'     => $expense->category->id,
                    'name'   => $expense->category->name,
                    'colour' => $expense->category->colour,
                ] : null,
                'submitted_by'  => $expense->submittedBy  ? ['id' => $expense->submittedBy->id,  'name' => $expense->submittedBy->name]  : null,
                'approved_by'   => $expense->approvedBy   ? ['id' => $expense->approvedBy->id,   'name' => $expense->approvedBy->name]   : null,
                'submitted_at'  => $expense->submitted_at?->toDateTimeString(),
                'approved_at'   => $expense->approved_at?->toDateTimeString(),
                'documents'     => $expense->documents->map(fn ($d) => [
                    'id'        => $d->id,
                    'file_name' => $d->file_name,
                    'file_path' => $d->file_path,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                ])->values(),
            ],
        ]);
    }
}
