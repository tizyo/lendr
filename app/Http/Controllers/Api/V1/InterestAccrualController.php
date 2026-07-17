<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\LoanInterestAccrual;
use App\Services\InterestAccrualService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InterestAccrualController extends BaseApiController
{
    public function __construct(private InterestAccrualService $service) {}

    /**
     * List accruals, optionally filtered by loan or date range.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoanInterestAccrual::with('loan:id,loan_number,borrower_id', 'loan.borrower:id,first_name,last_name')
            ->orderByDesc('accrual_date')
            ->orderByDesc('id');

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->loan_id);
        }
        if ($request->filled('date_from')) {
            $query->where('accrual_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('accrual_date', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->paginated($query->paginate(50), fn ($a) => $this->format($a));
    }

    /**
     * Run accrual for a given date.
     */
    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date'    => ['nullable', 'date'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $date   = isset($data['date']) ? Carbon::parse($data['date']) : now()->subDay();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = $this->service->accrueForDate($date, $dryRun);

        return $this->success($result, 'Interest accrual complete.');
    }

    /**
     * Monthly accrual summary for a year, plus dashboard card stats.
     */
    public function summary(Request $request): JsonResponse
    {
        $year  = (int) ($request->year ?? now()->year);
        $month = now()->month;
        $rows  = $this->service->monthlySummary($year);

        $totalYear  = round(array_sum(array_column($rows, 'total_accrued')), 2);
        $monthTotal = round((float) ($rows[$month - 1]['total_accrued'] ?? 0), 2);

        $activeLoans    = LoanInterestAccrual::distinct('loan_id')->count('loan_id');
        $nonPerforming  = LoanInterestAccrual::where('is_suspended', true)->distinct('loan_id')->count('loan_id');

        return $this->success([
            'active_loans'        => $activeLoans,
            'total_accrued_month' => $monthTotal,
            'non_performing'      => $nonPerforming,
            'total_accrued_all'   => $totalYear,
            'year'                => $year,
            'months'              => $rows,
            'annual_total'        => $totalYear,
        ]);
    }

    private function format(LoanInterestAccrual $a): array
    {
        $loan     = $a->relationLoaded('loan') ? $a->loan : null;
        $borrower = $loan?->relationLoaded('borrower') ? $loan->borrower : null;

        return [
            'id'                    => $a->id,
            'loan_id'               => $a->loan_id,
            'loan_number'           => $loan?->loan_number,
            'borrower_name'         => $borrower ? trim($borrower->first_name . ' ' . $borrower->last_name) : null,
            'accrual_date'          => $a->accrual_date?->toDateString(),
            'principal_outstanding' => (float) $a->principal_outstanding,
            'daily_rate'            => (float) $a->daily_rate,
            'accrued_amount'        => (float) $a->accrued_amount,
            'status'                => $a->status,
            'is_non_performing'     => (bool) $a->is_suspended,
        ];
    }
}
