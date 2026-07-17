<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\RegulatoryReport;
use App\Models\Tenant\RegulatoryReportConfig;
use App\Services\RegulatoryReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegulatoryReportController extends BaseApiController
{
    public function __construct(private readonly RegulatoryReportingService $reporting) {}

    private const VALID_TYPES = ['car', 'liquidity', 'large_exposure', 'par'];

    /** POST /api/v1/regulatory/generate */
    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'in:car,liquidity,large_exposure,par'],
            'period'      => ['required', 'string', 'max:20'], // e.g. '2026-03'
        ]);

        $report = $this->reporting->generate($data['report_type'], $data['period']);

        return $this->success($this->format($report), 'Report generated.', 201);
    }

    /** GET /api/v1/regulatory/reports */
    public function index(Request $request): JsonResponse
    {
        $query = RegulatoryReport::orderByDesc('created_at');

        if ($request->has('report_type')) {
            $query->where('report_type', $request->report_type);
        }

        $reports = $query->paginate(20);

        return $this->paginated($reports, fn ($r) => $this->format($r));
    }

    /** GET /api/v1/regulatory/reports/{report} */
    public function show(RegulatoryReport $report): JsonResponse
    {
        return $this->success($this->format($report));
    }

    /** POST /api/v1/regulatory/reports/{report}/email */
    public function email(Request $request, RegulatoryReport $report): JsonResponse
    {
        $data = $request->validate([
            'recipients'   => ['required', 'array', 'min:1'],
            'recipients.*' => ['email'],
        ]);

        $this->reporting->email($report, $data['recipients']);

        return $this->success(['emailed' => true, 'emailed_at' => now()->toDateTimeString()], 'Report emailed.');
    }

    /** GET /api/v1/regulatory/configs */
    public function configs(): JsonResponse
    {
        $configs = RegulatoryReportConfig::orderBy('report_type')->get()
            ->map(fn ($c) => $this->formatConfig($c));

        return $this->success($configs);
    }

    /** POST /api/v1/regulatory/configs */
    public function upsertConfig(Request $request): JsonResponse
    {
        $data = $request->validate([
            'report_type'      => ['required', 'in:car,liquidity,large_exposure,par'],
            'name'             => ['required', 'string', 'max:100'],
            'frequency'        => ['required', 'in:monthly,quarterly,on_demand'],
            'recipient_emails' => ['required', 'string'],
            'is_active'        => ['boolean'],
        ]);

        $config = RegulatoryReportConfig::updateOrCreate(
            ['report_type' => $data['report_type']],
            [
                'name'             => $data['name'],
                'frequency'        => $data['frequency'],
                'recipient_emails' => $data['recipient_emails'],
                'is_active'        => $data['is_active'] ?? true,
            ]
        );

        return $this->success($this->formatConfig($config), 'Config saved.', 201);
    }

    // ─── Private ──────────────────────────────────────────────────────────────

    private function format(RegulatoryReport $r): array
    {
        return [
            'id'           => $r->id,
            'report_type'  => $r->report_type,
            'period'       => $r->period,
            'data'         => $r->data,
            'generated_by' => $r->generated_by,
            'emailed'      => $r->emailed,
            'emailed_at'   => $r->emailed_at?->toDateTimeString(),
            'created_at'   => $r->created_at?->toDateTimeString(),
        ];
    }

    private function formatConfig(RegulatoryReportConfig $c): array
    {
        return [
            'id'               => $c->id,
            'report_type'      => $c->report_type,
            'name'             => $c->name,
            'frequency'        => $c->frequency,
            'recipient_emails' => $c->recipient_emails,
            'is_active'        => $c->is_active,
            'last_sent_at'     => $c->last_sent_at?->toDateTimeString(),
        ];
    }
}
