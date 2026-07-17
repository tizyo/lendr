<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ExpenseStatus;
use App\Models\Tenant\Expense;
use App\Services\FundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpenseController extends BaseApiController
{
    public function __construct(private FundService $fund) {}

    // ─── CRUD ────────────────────────────────────────────────────────────────

    public function index(Request $request): JsonResponse
    {
        $expenses = Expense::query()
            ->with([
                'category:id,name,code,colour',
                'submittedBy:id,name',
                'approvedBy:id,name',
            ])
            ->when($request->status,      fn ($q, $s) => $q->where('status', $s))
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->submitted_by, fn ($q, $id) => $q->where('submitted_by', $id))
            ->when($request->search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('expense_number', 'like', "%{$s}%")
                  ->orWhere('title', 'like', "%{$s}%")
                  ->orWhere('vendor', 'like', "%{$s}%");
            }))
            ->when($request->date_from, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->latest('expense_date')
            ->paginate(20);

        return $this->paginated($expenses, fn ($e) => $this->formatExpense($e));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'expense_category_id' => ['required', 'exists:expense_categories,id'],
            'title'               => ['required', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'amount'              => ['required', 'numeric', 'min:0.01'],
            'currency'            => ['nullable', 'string', 'max:3'],
            'payment_method'      => ['nullable', 'string', 'max:50'],
            'vendor'              => ['nullable', 'string', 'max:200'],
            'receipt_reference'   => ['nullable', 'string', 'max:100'],
            'expense_date'        => ['required', 'date'],
        ]);

        $expense = Expense::create([
            'expense_number'      => $this->generateNumber(),
            'expense_category_id' => $request->expense_category_id,
            'submitted_by'        => auth()->id(),
            'title'               => $request->title,
            'description'         => $request->description,
            'amount'              => $request->amount,
            'currency'            => $request->currency ?? 'ZMW',
            'payment_method'      => $request->payment_method,
            'vendor'              => $request->vendor,
            'receipt_reference'   => $request->receipt_reference,
            'expense_date'        => $request->expense_date,
            'status'              => ExpenseStatus::Draft,
        ]);

        return $this->success($this->formatExpense($expense->load(['category', 'submittedBy'])), 'Expense created.', 201);
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['category', 'submittedBy:id,name', 'approvedBy:id,name', 'documents']);

        return $this->success($this->formatExpense($expense, true));
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        if (! in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Rejected])) {
            return $this->error('Only draft or rejected expenses can be edited.', 422);
        }

        $request->validate([
            'expense_category_id' => ['sometimes', 'exists:expense_categories,id'],
            'title'               => ['sometimes', 'string', 'max:200'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'amount'              => ['sometimes', 'numeric', 'min:0.01'],
            'currency'            => ['nullable', 'string', 'max:3'],
            'payment_method'      => ['nullable', 'string', 'max:50'],
            'vendor'              => ['nullable', 'string', 'max:200'],
            'receipt_reference'   => ['nullable', 'string', 'max:100'],
            'expense_date'        => ['sometimes', 'date'],
        ]);

        $expense->update($request->only([
            'expense_category_id', 'title', 'description', 'amount',
            'currency', 'payment_method', 'vendor', 'receipt_reference', 'expense_date',
        ]));

        return $this->success($this->formatExpense($expense->fresh()->load(['category', 'submittedBy'])), 'Expense updated.');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        if (! in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Rejected])) {
            return $this->error('Only draft or rejected expenses can be deleted.', 422);
        }

        $expense->delete();

        return $this->success(null, 'Expense deleted.');
    }

    // ─── Lifecycle ────────────────────────────────────────────────────────────

    public function submit(Request $request, Expense $expense): JsonResponse
    {
        if (! in_array($expense->status, [ExpenseStatus::Draft, ExpenseStatus::Rejected])) {
            return $this->error('Only draft or rejected expenses can be submitted.', 422);
        }

        $expense->update([
            'status'       => ExpenseStatus::Pending,
            'submitted_at' => now(),
            'submitted_by' => auth()->id(),
        ]);

        return $this->success($this->formatExpense($expense->fresh()), 'Expense submitted for approval.');
    }

    public function approve(Request $request, Expense $expense): JsonResponse
    {
        if ($expense->status !== ExpenseStatus::Pending) {
            return $this->error('Only pending expenses can be approved.', 422);
        }

        if (! auth()->user()->can('expenses.approve')) {
            return $this->error('You do not have permission to approve expenses.', 403);
        }

        DB::transaction(function () use ($expense) {
            $expense->update([
                'status'      => ExpenseStatus::Approved,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);

            $this->fund->recordExpense($expense, (float) $expense->amount, auth()->id());
        });

        return $this->success($this->formatExpense($expense->fresh()), 'Expense approved.');
    }

    public function reject(Request $request, Expense $expense): JsonResponse
    {
        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:500'],
        ]);

        if ($expense->status !== ExpenseStatus::Pending) {
            return $this->error('Only pending expenses can be rejected.', 422);
        }

        $expense->update([
            'status'           => ExpenseStatus::Rejected,
            'rejection_reason' => $request->rejection_reason,
        ]);

        return $this->success($this->formatExpense($expense->fresh()), 'Expense rejected.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateNumber(): string
    {
        $prefix = 'EXP-'.now()->format('Ym').'-';
        $last   = Expense::withTrashed()->where('expense_number', 'like', $prefix.'%')->max('expense_number');
        $seq    = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function formatExpense(Expense $e, bool $full = false): array
    {
        $data = [
            'id'             => $e->id,
            'expense_number' => $e->expense_number,
            'title'          => $e->title,
            'amount'         => (float) $e->amount,
            'currency'       => $e->currency,
            'expense_date'   => $e->expense_date?->toDateString(),
            'status'         => $e->status instanceof ExpenseStatus ? $e->status->value : $e->status,
            'payment_method' => $e->payment_method,
            'vendor'         => $e->vendor,
            'category'       => $e->relationLoaded('category') && $e->category
                ? ['id' => $e->category->id, 'name' => $e->category->name, 'colour' => $e->category->colour]
                : null,
            'submitted_by'   => $e->relationLoaded('submittedBy') && $e->submittedBy
                ? ['id' => $e->submittedBy->id, 'name' => $e->submittedBy->name]
                : null,
            'submitted_at'   => $e->submitted_at?->toDateTimeString(),
            'approved_by'    => $e->relationLoaded('approvedBy') && $e->approvedBy
                ? ['id' => $e->approvedBy->id, 'name' => $e->approvedBy->name]
                : null,
            'approved_at'    => $e->approved_at?->toDateTimeString(),
        ];

        if ($full) {
            $data['description']      = $e->description;
            $data['receipt_reference'] = $e->receipt_reference;
            $data['rejection_reason'] = $e->rejection_reason;
            $data['documents']        = $e->relationLoaded('documents')
                ? $e->documents->map(fn ($d) => [
                    'id'        => $d->id,
                    'file_name' => $d->file_name,
                    'file_path' => $d->file_path,
                    'mime_type' => $d->mime_type,
                    'file_size' => $d->file_size,
                ])->values()
                : [];
        }

        return $data;
    }
}
