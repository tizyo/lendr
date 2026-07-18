<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanPenalty;
use App\Services\PenaltyService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PenaltyController extends BaseApiController
{
    public function __construct(private PenaltyService $service) {}

    public function index(Request $request): JsonResponse
    {
        $query = LoanPenalty::with('loan')->orderByDesc('penalty_date')->orderByDesc('id');

        if ($request->filled('loan_id')) {
            $query->where('loan_id', $request->loan_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return $this->paginated($query->paginate(50), fn ($p) => $this->format($p));
    }

    public function loanPenalties(Loan $loan): JsonResponse
    {
        $penalties = $loan->penalties()->orderByDesc('penalty_date')->get()
            ->map(fn ($p) => $this->format($p));

        return $this->success($penalties);
    }

    public function run(Request $request): JsonResponse
    {
        $data = $request->validate([
            'date' => ['nullable', 'date'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $date = isset($data['date']) ? Carbon::parse($data['date']) : now();
        $dryRun = (bool) ($data['dry_run'] ?? false);

        $result = $this->service->applyPenaltiesForDate($date, $dryRun);

        return $this->success($result, 'Penalties applied.');
    }

    public function waive(Request $request, LoanPenalty $penalty): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        if ($penalty->status === 'waived') {
            return $this->success(null, 'Penalty is already fully waived.', 422);
        }

        $updated = $this->service->waivePenalty(
            $penalty,
            $request->user(),
            (float) $data['amount'],
            $data['reason'],
        );

        return $this->success(['penalty' => $this->format($updated)], 'Penalty waived.');
    }

    private function format(LoanPenalty $p): array
    {
        return [
            'id' => $p->id,
            'loan_id' => $p->loan_id,
            'schedule_id' => $p->schedule_id,
            'penalty_date' => $p->penalty_date?->toDateString(),
            'days_overdue' => $p->days_overdue,
            'penalty_rate' => (float) $p->penalty_rate,
            'overdue_amount' => (float) $p->overdue_amount,
            'penalty_amount' => (float) $p->penalty_amount,
            'waived_amount' => (float) $p->waived_amount,
            'waiver_reason' => $p->waiver_reason,
            'status' => $p->status,
            'waived_at' => $p->waived_at?->toDateTimeString(),
        ];
    }
}
