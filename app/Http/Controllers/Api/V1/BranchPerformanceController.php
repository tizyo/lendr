<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Branch;
use App\Services\BranchPerformanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BranchPerformanceController extends BaseApiController
{
    public function __construct(private BranchPerformanceService $svc) {}

    /**
     * GET /branches/{branch}/performance/pnl?period=2026-03
     */
    public function pnl(Request $request, Branch $branch): JsonResponse
    {
        $period = $request->get('period', 'all');

        // Validate period format if provided
        if ($period !== 'all' && ! preg_match('/^\d{4}-\d{2}$/', $period)) {
            return $this->error('Invalid period format. Use YYYY-MM or "all".', 422);
        }

        return $this->success([
            'branch' => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'pnl'    => $this->svc->pnl($branch, $period),
        ]);
    }

    /**
     * GET /branches/{branch}/performance/portfolio
     */
    public function portfolio(Branch $branch): JsonResponse
    {
        return $this->success([
            'branch'    => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'portfolio' => $this->svc->portfolioHealth($branch),
        ]);
    }

    /**
     * GET /branches/{branch}/performance/officers?period=2026-03
     */
    public function officers(Request $request, Branch $branch): JsonResponse
    {
        $period = $request->get('period');

        return $this->success([
            'branch'  => ['id' => $branch->id, 'name' => $branch->name, 'code' => $branch->code],
            'officers'=> $this->svc->officerLeague($branch, $period),
        ]);
    }
}
