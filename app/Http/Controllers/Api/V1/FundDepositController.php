<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\FundDeposit;
use App\Services\FundService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FundDepositController extends BaseApiController
{
    public function __construct(private FundService $fund) {}

    public function index(Request $request): JsonResponse
    {
        $deposits = FundDeposit::query()
            ->with(['depositedBy:id,name', 'approvedBy:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->where('deposit_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('deposit_date', '<=', $d))
            ->latest('deposit_date')
            ->paginate(20);

        return $this->paginated($deposits, fn ($d) => $this->formatDeposit($d));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'source'         => ['required', 'string', 'max:200'],
            'payment_method' => ['required', 'in:cash,bank_transfer,cheque'],
            'bank_reference' => ['nullable', 'string', 'max:100'],
            'deposit_date'   => ['required', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $deposit = FundDeposit::create([
            'reference'      => $this->generateReference(),
            'amount'         => $request->amount,
            'source'         => $request->source,
            'payment_method' => $request->payment_method,
            'bank_reference' => $request->bank_reference,
            'deposit_date'   => $request->deposit_date,
            'notes'          => $request->notes,
            'deposited_by'   => auth()->id(),
            'status'         => 'pending',
        ]);

        return $this->success($this->formatDeposit($deposit->load('depositedBy')), 'Deposit recorded.', 201);
    }

    public function show(FundDeposit $deposit): JsonResponse
    {
        $deposit->load(['depositedBy:id,name', 'approvedBy:id,name']);

        return $this->success($this->formatDeposit($deposit, true));
    }

    public function update(Request $request, FundDeposit $deposit): JsonResponse
    {
        if ($deposit->status !== 'pending') {
            return $this->error('Only pending deposits can be edited.', 422);
        }

        $request->validate([
            'amount'         => ['sometimes', 'numeric', 'min:0.01'],
            'source'         => ['sometimes', 'string', 'max:200'],
            'payment_method' => ['sometimes', 'in:cash,bank_transfer,cheque'],
            'bank_reference' => ['nullable', 'string', 'max:100'],
            'deposit_date'   => ['sometimes', 'date'],
            'notes'          => ['nullable', 'string', 'max:1000'],
        ]);

        $deposit->update($request->only(['amount', 'source', 'payment_method', 'bank_reference', 'deposit_date', 'notes']));

        return $this->success($this->formatDeposit($deposit->fresh()), 'Deposit updated.');
    }

    public function destroy(FundDeposit $deposit): JsonResponse
    {
        if ($deposit->status !== 'pending') {
            return $this->error('Only pending deposits can be deleted.', 422);
        }

        $deposit->delete();

        return $this->success(null, 'Deposit deleted.');
    }

    /**
     * POST /api/v1/funds/deposits/{deposit}/approve
     */
    public function approve(Request $request, FundDeposit $deposit): JsonResponse
    {
        if (! auth()->user()?->can('funds.approve')) {
            return $this->error('Forbidden.', 403);
        }

        if ($deposit->status !== 'pending') {
            return $this->error('Only pending deposits can be approved.', 422);
        }

        DB::transaction(function () use ($deposit, $request) {
            $deposit->update([
                'status'      => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'notes'       => $request->notes ? ($deposit->notes."\n".$request->notes) : $deposit->notes,
            ]);

            $this->fund->recordDeposit($deposit, auth()->id());
        });

        return $this->success($this->formatDeposit($deposit->fresh()->load('approvedBy')), 'Deposit approved and credited to fund.');
    }

    /**
     * POST /api/v1/funds/deposits/{deposit}/reject
     */
    public function reject(Request $request, FundDeposit $deposit): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        if ($deposit->status !== 'pending') {
            return $this->error('Only pending deposits can be rejected.', 422);
        }

        $deposit->update([
            'status' => 'rejected',
            'notes'  => $deposit->notes
                ? $deposit->notes."\nRejection reason: ".$request->reason
                : 'Rejection reason: '.$request->reason,
        ]);

        return $this->success($this->formatDeposit($deposit->fresh()), 'Deposit rejected.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function generateReference(): string
    {
        $prefix = 'DEP-'.now()->format('Ym').'-';
        $last   = FundDeposit::where('reference', 'like', $prefix.'%')->max('reference');
        $seq    = $last ? ((int) Str::afterLast($last, '-')) + 1 : 1;

        return $prefix.str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    private function formatDeposit(FundDeposit $d, bool $full = false): array
    {
        $data = [
            'id'             => $d->id,
            'reference'      => $d->reference,
            'amount'         => (float) $d->amount,
            'source'         => $d->source,
            'payment_method' => $d->payment_method,
            'bank_reference' => $d->bank_reference,
            'deposit_date'   => $d->deposit_date->toDateString(),
            'status'         => $d->status,
            'deposited_by'   => $d->relationLoaded('depositedBy') ? $d->depositedBy?->name : null,
            'approved_by'    => $d->relationLoaded('approvedBy') ? $d->approvedBy?->name : null,
            'approved_at'    => $d->approved_at?->toDateTimeString(),
            'created_at'     => $d->created_at->format('d M Y'),
        ];

        if ($full) {
            $data['notes'] = $d->notes;
        }

        return $data;
    }
}
