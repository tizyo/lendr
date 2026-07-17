<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\TaxConfiguration;
use App\Models\Tenant\TaxComputation;
use App\Services\TaxComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxComplianceController extends BaseApiController
{
    public function __construct(private TaxComplianceService $tax) {}

    // ─── Tax Configuration ────────────────────────────────────────────────────

    /**
     * GET /api/v1/tax/configurations
     */
    public function configurations(): JsonResponse
    {
        $configs = TaxConfiguration::orderBy('tax_type')->get()
            ->map(fn ($c) => $this->formatConfig($c));

        return $this->success(['configurations' => $configs]);
    }

    /**
     * POST /api/v1/tax/configurations
     */
    public function storeConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tax_type'            => ['required', 'string', 'in:wht,vat,excise'],
            'rate'                => ['required', 'numeric', 'min:0', 'max:100'],
            'label'               => ['nullable', 'string', 'max:100'],
            'applies_to_interest' => ['boolean'],
            'applies_to_fees'     => ['boolean'],
            'is_active'           => ['boolean'],
        ]);

        $config = TaxConfiguration::create($data);

        return $this->success(['configuration' => $this->formatConfig($config)], 'Tax configuration saved.', 201);
    }

    /**
     * PUT /api/v1/tax/configurations/{config}
     */
    public function updateConfig(Request $request, TaxConfiguration $config): JsonResponse
    {
        $data = $request->validate([
            'rate'                => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'label'               => ['nullable', 'string', 'max:100'],
            'applies_to_interest' => ['boolean'],
            'applies_to_fees'     => ['boolean'],
            'is_active'           => ['boolean'],
        ]);

        $config->update($data);

        return $this->success(['configuration' => $this->formatConfig($config->fresh())], 'Tax configuration updated.');
    }

    // ─── Reports ──────────────────────────────────────────────────────────────

    /**
     * GET /api/v1/tax/wht-summary?from=2026-01&to=2026-03
     */
    public function whtSummary(Request $request): JsonResponse
    {
        $request->validate([
            'from' => ['required', 'regex:/^\d{4}-\d{2}$/'],
            'to'   => ['required', 'regex:/^\d{4}-\d{2}$/'],
        ]);

        $summary = $this->tax->whtSummary($request->from, $request->to);

        return $this->success(['summary' => $summary]);
    }

    /**
     * POST /api/v1/tax/wht-summary/{period}/remit
     */
    public function markRemitted(string $period): JsonResponse
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $this->error('Invalid period format. Use YYYY-MM.', 422);
        }

        $count = $this->tax->markRemitted($period);

        return $this->success(['updated' => $count], "{$count} record(s) marked as remitted.");
    }

    /**
     * GET /api/v1/tax/par-report
     */
    public function parReport(): JsonResponse
    {
        return $this->success($this->tax->parReport());
    }

    /**
     * GET /api/v1/tax/capital-adequacy
     */
    public function capitalAdequacy(): JsonResponse
    {
        return $this->success($this->tax->capitalAdequacy());
    }

    /**
     * GET /api/v1/tax/computations?period=2026-03&status=computed
     */
    public function computations(Request $request): JsonResponse
    {
        $query = TaxComputation::with('taxConfiguration')
            ->when($request->period, fn ($q, $v) => $q->where('period', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->tax_type, fn ($q, $v) => $q
                ->whereHas('taxConfiguration', fn ($tq) => $tq->where('tax_type', $v)))
            ->orderByDesc('id');

        return $this->paginated(
            $query->paginate($request->integer('per_page', 50)),
            fn ($c) => [
                'id'             => $c->id,
                'tax_type'       => $c->taxConfiguration?->tax_type,
                'source_type'    => $c->source_type,
                'source_id'      => $c->source_id,
                'taxable_amount' => (float) $c->taxable_amount,
                'tax_amount'     => (float) $c->tax_amount,
                'period'         => $c->period,
                'status'         => $c->status,
                'remitted_at'    => $c->remitted_at?->toDateTimeString(),
            ]
        );
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function formatConfig(TaxConfiguration $c): array
    {
        return [
            'id'                  => $c->id,
            'tax_type'            => $c->tax_type,
            'rate'                => (float) $c->rate,
            'label'               => $c->label,
            'applies_to_interest' => $c->applies_to_interest,
            'applies_to_fees'     => $c->applies_to_fees,
            'is_active'           => $c->is_active,
        ];
    }
}
