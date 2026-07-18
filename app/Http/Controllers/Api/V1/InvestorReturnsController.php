<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Investor;
use App\Models\Tenant\InvestorDividend;
use App\Services\InvestorReturnsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvestorReturnsController extends BaseApiController
{
    public function __construct(private InvestorReturnsService $service) {}

    /**
     * GET /api/v1/investors/{investor}/dividends
     */
    public function index(Investor $investor): JsonResponse
    {
        $dividends = $investor->dividends()->orderByDesc('period')->get();

        return $this->success([
            'dividends' => $dividends->map(fn ($d) => $this->formatDividend($d)),
            'summary' => $this->service->summary($investor),
        ]);
    }

    /**
     * POST /api/v1/investors/{investor}/dividends
     */
    public function calculate(Request $request, Investor $investor): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'annual_rate_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'allocation_id' => ['nullable', 'exists:investor_allocations,id'],
        ]);

        $dividend = $this->service->calculateDividend(
            $investor,
            $data['period'],
            (float) ($data['annual_rate_pct'] ?? 12.0),
            $data['allocation_id'] ?? null,
        );

        return $this->success(['dividend' => $this->formatDividend($dividend)], 'Dividend calculated.', 201);
    }

    /**
     * POST /api/v1/investor-dividends/{dividend}/pay
     */
    public function pay(InvestorDividend $dividend): JsonResponse
    {
        if ($dividend->status === 'paid') {
            return $this->error('Dividend already paid.', 422);
        }

        $dividend = $this->service->markPaid($dividend, auth()->id());

        return $this->success(['dividend' => $this->formatDividend($dividend)], 'Dividend marked as paid.');
    }

    /**
     * DELETE /api/v1/investor-dividends/{dividend}
     * Cancel a pending dividend.
     */
    public function cancel(InvestorDividend $dividend): JsonResponse
    {
        if ($dividend->status !== 'pending') {
            return $this->error('Only pending dividends can be cancelled.', 422);
        }

        $dividend->update(['status' => 'cancelled']);

        return $this->success(['dividend' => $this->formatDividend($dividend->fresh())], 'Dividend cancelled.');
    }

    // ─── Formatter ────────────────────────────────────────────────────────────

    private function formatDividend(InvestorDividend $d): array
    {
        return [
            'id' => $d->id,
            'investor_id' => $d->investor_id,
            'allocation_id' => $d->allocation_id,
            'period' => $d->period,
            'principal' => (float) $d->principal,
            'return_rate' => (float) $d->return_rate,
            'gross_dividend' => (float) $d->gross_dividend,
            'tax_withheld' => (float) $d->tax_withheld,
            'net_dividend' => (float) $d->net_dividend,
            'status' => $d->status,
            'paid_date' => $d->paid_date?->toDateString(),
            'processed_by' => $d->processed_by,
        ];
    }
}
