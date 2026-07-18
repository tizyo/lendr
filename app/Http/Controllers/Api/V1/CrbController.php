<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Landlord\CrbInquiry;
use App\Models\Tenant\Borrower;
use App\Services\CrbService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Tenant-facing CRB API.
 * Exposes credit checks and score history without leaking cross-tenant PII.
 */
class CrbController extends BaseApiController
{
    public function __construct(private CrbService $crb) {}

    /**
     * POST /crb/check
     * Check the CRB profile for a given identifier.
     * Payload: { type: 'nrc'|'tpin'|'company_reg', value: string }
     */
    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['nrc', 'tpin', 'company_reg'])],
            'value' => ['required', 'string', 'max:50'],
        ]);

        $tenantId = tenant('id') ?? 'unknown';
        $hash = $this->crb->hash($data['value'], $data['type']);
        $result = $this->crb->check($hash, $data['type'], $tenantId, 'manual_check');

        return $this->success(array_merge($result, ['identity_hash' => $hash]));
    }

    /**
     * GET /borrowers/{borrower}/crb
     * Check the CRB profile for a specific borrower using their stored identifiers.
     */
    public function borrowerReport(Request $request, Borrower $borrower): JsonResponse
    {
        $identifier = $borrower->crbIdentifier();

        if (! $identifier) {
            return $this->error('Borrower has no CRB identifier (NRC/TPIN/Company Reg) on file.', 422);
        }

        $tenantId = tenant('id') ?? 'unknown';
        $hash = $this->crb->hash($identifier['value'], $identifier['type']);
        $result = $this->crb->check($hash, $identifier['type'], $tenantId, 'manual_check');

        // Enrich with all identifier checks if multiple exist
        $allResults = [];
        foreach ($borrower->allCrbIdentifiers() as $id) {
            $h = $this->crb->hash($id['value'], $id['type']);
            $r = $this->crb->check($h, $id['type'], $tenantId, 'manual_check');
            $allResults[$id['type']] = $r;
        }

        return $this->success([
            'primary' => $result,
            'by_type' => $allResults,
            'borrower_id' => $borrower->id,
        ]);
    }

    /**
     * POST /borrowers/{borrower}/crb/update
     * Manually trigger a CRB score recalculation for a borrower (admin use).
     */
    public function recalculate(Borrower $borrower): JsonResponse
    {
        $identifier = $borrower->crbIdentifier();

        if (! $identifier) {
            return $this->error('Borrower has no CRB identifier on file.', 422);
        }

        $hash = $this->crb->hash($identifier['value'], $identifier['type']);
        $identity = $this->crb->getOrCreate($hash, $identifier['type']);
        $this->crb->recalculateFromEvents($identity);

        return $this->success([
            'credit_score' => $identity->fresh()->credit_score,
            'score_band' => $identity->fresh()->score_band,
        ], 'Score recalculated.');
    }

    /**
     * GET /crb/inquiries
     * Recent CRB inquiries made by the current tenant.
     */
    public function inquiries(Request $request): JsonResponse
    {
        $tenantId = tenant('id') ?? 'unknown';

        $rows = CrbInquiry::where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn ($inq) => [
                'id' => $inq->id,
                'identity_hash' => $inq->identity_hash,
                'purpose' => $inq->purpose,
                'score_at_inquiry' => $inq->result_score,
                'risk_level' => $inq->result_risk_level,
                'active_loans' => $inq->result_active_loans,
                'has_active_loans' => $inq->result_has_active_loans,
                'created_at' => $inq->created_at?->toIso8601String(),
            ]);

        return $this->success($rows);
    }

    /**
     * GET /crb/report/{hash}
     * Full CRB report by hash — for landlord admin tools.
     */
    public function report(string $hash): JsonResponse
    {
        $report = $this->crb->fullReport($hash);

        if (! $report) {
            return $this->success(null, 'No CRB record found for this identifier.');
        }

        return $this->success($report);
    }
}
