<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanWriteoff;
use App\Services\GlLedgerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WriteoffController extends BaseApiController
{
    public function __construct(private GlLedgerService $gl) {}

    /**
     * GET /api/v1/loans/{loan}/writeoff
     * Return write-off details and recovery history for a written-off loan.
     */
    public function show(Loan $loan): JsonResponse
    {
        $writeoff = LoanWriteoff::with(['writtenOffBy', 'recoveries.recordedBy'])
            ->where('loan_id', $loan->id)
            ->first();

        if (! $writeoff) {
            return $this->error('No write-off record found for this loan.', 404);
        }

        return $this->success($this->formatWriteoff($writeoff));
    }

    /**
     * POST /api/v1/loans/{loan}/recovery
     * Record a recovery payment on a written-off loan.
     */
    public function recovery(Request $request, Loan $loan): JsonResponse
    {
        $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'method'        => ['required', 'in:cash,bank_transfer,mobile_money'],
            'reference'     => ['nullable', 'string', 'max:100'],
            'notes'         => ['nullable', 'string', 'max:500'],
            'recovery_date' => ['nullable', 'date'],
        ]);

        $writeoff = LoanWriteoff::where('loan_id', $loan->id)->first();

        if (! $writeoff) {
            return $this->error('No write-off record found for this loan.', 404);
        }

        $recovery = $writeoff->recoveries()->create([
            'recorded_by'   => auth()->id(),
            'amount'        => $request->amount,
            'method'        => $request->method,
            'reference'     => $request->reference,
            'notes'         => $request->notes,
            'recovery_date' => $request->recovery_date ?? now()->toDateString(),
        ]);

        // Update total recovered on writeoff record
        $writeoff->increment('total_recovered', $request->amount);

        // GL entry: DR Cash | CR Bad Debt Recovery (or Write-Off Expense reversal)
        try {
            $this->gl->post(
                "Write-off recovery: {$loan->loan_number}",
                [
                    ['account_code' => '1001', 'side' => 'debit',  'amount' => $request->amount, 'notes' => 'Recovery received'],
                    ['account_code' => '5003', 'side' => 'credit', 'amount' => $request->amount, 'notes' => 'Write-off expense reversal'],
                ],
                $loan,
                $recovery->recovery_date->toDateString(),
                auth()->id()
            );
        } catch (\Throwable) {
            // GL accounts may not be seeded; do not block recovery
        }

        return $this->success([
            'recovery'        => $this->formatRecovery($recovery),
            'total_recovered' => (float) $writeoff->fresh()->total_recovered,
        ], 'Recovery recorded.', 201);
    }

    /**
     * GET /api/v1/writeoffs
     * List all written-off loans with recovery info.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoanWriteoff::with(['loan.borrower', 'writtenOffBy'])
            ->when($request->date_from, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to,   fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at');

        $paginator = $query->paginate($request->integer('per_page', 20));

        return $this->paginated($paginator, fn ($w) => $this->formatWriteoff($w));
    }

    // ─── Format helpers ───────────────────────────────────────────────────────

    private function formatWriteoff(LoanWriteoff $w): array
    {
        return [
            'id'                  => $w->id,
            'loan_id'             => $w->loan_id,
            'loan_number'         => $w->loan?->loan_number,
            'borrower'            => $w->loan?->borrower ? [
                'id'   => $w->loan->borrower->id,
                'name' => $w->loan->borrower->full_name,
            ] : null,
            'written_off_by'      => $w->writtenOffBy?->name,
            'written_off_amount'  => (float) $w->written_off_amount,
            'total_recovered'     => (float) $w->total_recovered,
            'net_loss'            => $w->netLoss(),
            'recovery_rate'       => $w->recoveryRate(),
            'reason'              => $w->reason,
            'written_off_at'      => $w->created_at->toDateString(),
            'recoveries'          => $w->relationLoaded('recoveries')
                ? $w->recoveries->map(fn ($r) => $this->formatRecovery($r))->values()
                : [],
        ];
    }

    private function formatRecovery(\App\Models\Tenant\LoanWriteoffRecovery $r): array
    {
        return [
            'id'            => $r->id,
            'amount'        => (float) $r->amount,
            'method'        => $r->method,
            'reference'     => $r->reference,
            'notes'         => $r->notes,
            'recovery_date' => $r->recovery_date->toDateString(),
            'recorded_by'   => $r->recordedBy?->name,
        ];
    }
}
