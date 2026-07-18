<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanProvision;
use App\Models\Tenant\ProvisionRate;
use App\Services\ProvisioningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProvisioningController extends BaseApiController
{
    public function __construct(private ProvisioningService $service) {}

    // ─── Provision Rates ──────────────────────────────────────────────────────

    public function rates(): JsonResponse
    {
        $rates = ProvisionRate::where('is_active', true)
            ->orderBy('dpd_from')
            ->get()
            ->map(fn ($r) => $this->formatRate($r));

        return $this->success($rates);
    }

    public function storeRate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stage_label' => ['required', 'string', 'max:30'],
            'stage' => ['required', 'integer', 'in:1,2,3'],
            'dpd_from' => ['required', 'integer', 'min:0'],
            'dpd_to' => ['required', 'integer', 'gte:dpd_from'],
            'provision_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $rate = ProvisionRate::create($data);

        return $this->success(['rate' => $this->formatRate($rate)], 'Provision rate created.', 201);
    }

    public function updateRate(Request $request, ProvisionRate $rate): JsonResponse
    {
        $data = $request->validate([
            'stage_label' => ['sometimes', 'string', 'max:30'],
            'provision_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $rate->update($data);

        return $this->success(['rate' => $this->formatRate($rate->fresh())]);
    }

    public function seedRates(): JsonResponse
    {
        ProvisionRate::seedDefaults();

        return $this->success($this->rates()->getData(true)['data'], 'Default IFRS9 rates seeded.');
    }

    // ─── Loan-level Calculation ───────────────────────────────────────────────

    public function calculateLoan(Request $request, Loan $loan): JsonResponse
    {
        $provision = $this->service->calculateForLoan($loan, $request->user()->id);

        return $this->success(['provision' => $this->formatProvision($provision)], 'Provision calculated.', 201);
    }

    public function loanHistory(Loan $loan): JsonResponse
    {
        $provisions = $loan->provisions()
            ->orderByDesc('calculation_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn ($p) => $this->formatProvision($p));

        return $this->success($provisions);
    }

    // ─── Portfolio Run ─────────────────────────────────────────────────────────

    public function runPortfolio(Request $request): JsonResponse
    {
        $summary = $this->service->runPortfolio($request->user()->id);

        return $this->success($summary, 'Portfolio provisioning complete.');
    }

    public function portfolioSummary(): JsonResponse
    {
        $latest = LoanProvision::selectRaw('loan_id, MAX(id) as max_id')
            ->groupBy('loan_id')
            ->pluck('max_id');

        $provisions = LoanProvision::whereIn('id', $latest)
            ->with('loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name')
            ->get();

        $totalOutstanding = (float) $provisions->sum('outstanding_balance');
        $totalProvision = (float) $provisions->sum('provision_amount');
        $coverageRatio = $totalOutstanding > 0
            ? round(($totalProvision / $totalOutstanding) * 100, 2)
            : 0;

        $loans = $provisions->map(fn ($p) => [
            'loan_id' => $p->loan_id,
            'loan_number' => $p->loan?->loan_number,
            'borrower_name' => $p->loan?->borrower
                ? trim($p->loan->borrower->first_name.' '.$p->loan->borrower->last_name)
                : null,
            'outstanding' => (float) $p->outstanding_balance,
            'max_dpd' => $p->days_past_due,
            'stage' => $p->stage,
            'stage_label' => $p->stage_label,
            'provision_rate' => (float) $p->provision_rate,
            'provision_amount' => (float) $p->provision_amount,
        ]);

        return $this->success([
            'total_provision' => round($totalProvision, 2),
            'total_outstanding' => round($totalOutstanding, 2),
            'total_loans' => $provisions->count(),
            'coverage_ratio' => $coverageRatio,
            'summary' => [
                'total_loans' => $provisions->count(),
                'total_outstanding' => round($totalOutstanding, 2),
                'total_provision' => round($totalProvision, 2),
                'coverage_ratio' => $coverageRatio,
            ],
            'loans' => $loans,
        ]);
    }

    // ─── Formatters ───────────────────────────────────────────────────────────

    private function formatRate(ProvisionRate $r): array
    {
        return [
            'id' => $r->id,
            'stage' => $r->stage,
            'stage_label' => $r->stage_label,
            'dpd_from' => $r->dpd_from,
            'dpd_to' => $r->dpd_to,
            'provision_rate' => (float) $r->provision_rate,
            'is_active' => $r->is_active,
        ];
    }

    private function formatProvision(LoanProvision $p): array
    {
        return [
            'id' => $p->id,
            'loan_id' => $p->loan_id,
            'stage' => $p->stage,
            'stage_label' => $p->stage_label,
            'days_past_due' => $p->days_past_due,
            'outstanding_balance' => (float) $p->outstanding_balance,
            'provision_rate' => (float) $p->provision_rate,
            'provision_amount' => (float) $p->provision_amount,
            'calculation_date' => $p->calculation_date?->toDateString(),
            'notes' => $p->notes,
        ];
    }
}
