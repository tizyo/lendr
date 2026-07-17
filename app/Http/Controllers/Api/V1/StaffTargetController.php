<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use App\Models\Tenant\StaffTarget;
use App\Models\Tenant\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffTargetController extends BaseApiController
{
    /**
     * GET /api/v1/staff-targets
     */
    public function index(Request $request): JsonResponse
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $query = StaffTarget::with('user')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->orderBy('user_id');

        $targets = $query->get()->map(fn ($t) => $this->formatTarget($t, true));

        return $this->success([
            'period' => ['month' => $month, 'year' => $year],
            'targets' => $targets,
        ]);
    }

    /**
     * POST /api/v1/staff-targets
     */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id'              => ['required', 'exists:users,id'],
            'period_month'         => ['required', 'integer', 'min:1', 'max:12'],
            'period_year'          => ['required', 'integer', 'min:2020', 'max:2100'],
            'disbursement_target'  => ['nullable', 'numeric', 'min:0'],
            'collection_target'    => ['nullable', 'numeric', 'min:0'],
            'new_borrowers_target' => ['nullable', 'integer', 'min:0'],
            'new_loans_target'     => ['nullable', 'integer', 'min:0'],
            'notes'                => ['nullable', 'string'],
        ]);

        $target = StaffTarget::updateOrCreate(
            ['user_id' => $data['user_id'], 'period_month' => $data['period_month'], 'period_year' => $data['period_year']],
            $data
        );

        return $this->success(['target' => $this->formatTarget($target->load('user'), true)], 'Target saved.', 201);
    }

    /**
     * DELETE /api/v1/staff-targets/{target}
     */
    public function destroy(StaffTarget $staffTarget): JsonResponse
    {
        $staffTarget->delete();

        return $this->success(null, 'Target deleted.');
    }

    /**
     * GET /api/v1/staff-targets/performance
     * Team-level performance dashboard.
     */
    public function performance(Request $request): JsonResponse
    {
        $year  = $request->integer('year', now()->year);
        $month = $request->integer('month', now()->month);

        $targets = StaffTarget::with('user')
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->get();

        // Staff with no explicit targets – include them too if requested
        $teamData = $targets->map(fn ($t) => $this->formatTarget($t, true))->values();

        // Aggregate totals
        $totals = [
            'disbursement_target'  => $targets->sum('disbursement_target'),
            'collection_target'    => $targets->sum('collection_target'),
            'new_borrowers_target' => $targets->sum('new_borrowers_target'),
            'new_loans_target'     => $targets->sum('new_loans_target'),
            'disbursement_actual'  => $teamData->sum(fn ($t) => $t['actuals']['disbursement_actual'] ?? 0),
            'collection_actual'    => $teamData->sum(fn ($t) => $t['actuals']['collection_actual'] ?? 0),
            'new_borrowers_actual' => $teamData->sum(fn ($t) => $t['actuals']['new_borrowers_actual'] ?? 0),
            'new_loans_actual'     => $teamData->sum(fn ($t) => $t['actuals']['new_loans_actual'] ?? 0),
        ];

        return $this->success([
            'period'  => ['month' => $month, 'year' => $year],
            'team'    => $teamData,
            'totals'  => $totals,
        ]);
    }

    // ─── Formatter ────────────────────────────────────────────────────────────

    private function formatTarget(StaffTarget $t, bool $withActuals = false): array
    {
        $data = [
            'id'                   => $t->id,
            'user_id'              => $t->user_id,
            'user_name'            => $t->relationLoaded('user') ? $t->user->name : null,
            'period_month'         => $t->period_month,
            'period_year'          => $t->period_year,
            'disbursement_target'  => (float) $t->disbursement_target,
            'collection_target'    => (float) $t->collection_target,
            'new_borrowers_target' => (int) $t->new_borrowers_target,
            'new_loans_target'     => (int) $t->new_loans_target,
            'notes'                => $t->notes,
        ];

        if ($withActuals) {
            $data['actuals']      = $t->actuals();
            $data['achievement']  = $t->achievementRate();
        }

        return $data;
    }
}
