<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\LoanStatus;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Expense;
use App\Models\Tenant\FundBalance;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends BaseApiController
{
    /**
     * GET /api/v1/dashboard/kpis
     * Summary KPIs for the home dashboard.
     */
    public function kpis(): JsonResponse
    {
        $tenantId = tenant('id');
        $cacheKey = "dashboard_kpis_{$tenantId}";

        $payload = Cache::remember($cacheKey, 300, function () {
            $activeLoans = Loan::active()->get();
            $overdueLoans = Loan::overdue()->get();

            return $this->buildKpiPayload($activeLoans, $overdueLoans);
        });

        return $this->success($payload);
    }

    private function buildKpiPayload($activeLoans, $overdueLoans): array
    {

        $totalOutstanding = $activeLoans->sum(fn ($l) => (float) $l->outstanding_balance);
        $par = $activeLoans->count() > 0
            ? round(($overdueLoans->count() / $activeLoans->count()) * 100, 2)
            : 0;

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd = now()->endOfMonth()->toDateString();

        $disbursedMonth = (float) Loan::whereBetween('disbursement_date', [$monthStart, $monthEnd])->sum('principal_amount');
        $collectedMonth = (float) Payment::whereBetween('payment_date', [$monthStart, $monthEnd])->sum('amount');
        $expensesMonth = (float) Expense::where('status', 'approved')
            ->whereBetween('expense_date', [$monthStart, $monthEnd])
            ->sum('amount');

        $fund = FundBalance::current();

        $totalCapital = (float) $fund->total_deposits + (float) $fund->opening_balance;
        $utilization = $totalCapital > 0
            ? round(((float) $fund->total_disbursed / $totalCapital) * 100, 2)
            : 0;

        return [
            'active_loans' => $activeLoans->count(),
            'overdue_loans' => $overdueLoans->count(),
            'total_borrowers' => Borrower::where('is_active', true)->count(),
            'total_outstanding' => $totalOutstanding,
            'disbursed_month' => $disbursedMonth,
            'collected_month' => $collectedMonth,
            'expenses_month' => $expensesMonth,
            'par_30' => $par,
            'currency' => $fund->currency ?? 'ZMW',
            // Kept at top-level for backward compat
            'available_balance' => (float) $fund->available_balance,
            // Full fund breakdown
            'fund' => [
                'available_balance' => (float) $fund->available_balance,
                'total_deposits' => (float) $fund->total_deposits,
                'opening_balance' => (float) $fund->opening_balance,
                'total_disbursed' => (float) $fund->total_disbursed,
                'total_repaid' => (float) $fund->total_repaid,
                'total_penalties' => (float) $fund->total_penalties,
                'total_expenses' => (float) $fund->total_expenses,
                'net_position' => round(
                    (float) $fund->available_balance
                    - (float) $fund->total_disbursed
                    + (float) $fund->total_repaid,
                    2,
                ),
                'utilization_rate' => $utilization,
                'last_reconciled_at' => $fund->last_reconciled_at?->toDateTimeString(),
            ],
        ];
    }

    /**
     * GET /api/v1/dashboard/charts/{type}
     * Time-series chart data. Types: disbursements | repayments | expenses | par
     */
    public function chart(Request $request, string $type): JsonResponse
    {
        $months = max(1, min(24, (int) $request->query('months', 12)));

        $data = match ($type) {
            'disbursements' => $this->disbursementsChart($months),
            'repayments' => $this->repaymentsChart($months),
            'expenses' => $this->expensesChart($months),
            'par' => $this->parChart($months),
            default => null,
        };

        if ($data === null) {
            return $this->error("Unknown chart type '{$type}'. Valid: disbursements, repayments, expenses, par.", 422);
        }

        return $this->success(['type' => $type, 'series' => $data]);
    }

    // ─── Chart builders ──────────────────────────────────────────────────────

    private function disbursementsChart(int $months): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Loan::select('disbursement_date', 'principal_amount')
            ->whereNotNull('disbursement_date')
            ->where('disbursement_date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn ($l) => Carbon::parse($l->disbursement_date)->format('Y-m'));

        return $this->fillMonths($months, $rows, fn ($group) => [
            'total' => round($group->sum(fn ($l) => (float) $l->principal_amount), 2),
            'count' => $group->count(),
        ]);
    }

    private function repaymentsChart(int $months): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Payment::select('payment_date', 'amount')
            ->where('payment_date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn ($p) => Carbon::parse($p->payment_date)->format('Y-m'));

        return $this->fillMonths($months, $rows, fn ($group) => [
            'total' => round($group->sum(fn ($p) => (float) $p->amount), 2),
            'count' => $group->count(),
        ]);
    }

    private function expensesChart(int $months): array
    {
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Expense::select('expense_date', 'amount')
            ->where('status', 'approved')
            ->where('expense_date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn ($e) => Carbon::parse($e->expense_date)->format('Y-m'));

        return $this->fillMonths($months, $rows, fn ($group) => [
            'total' => round($group->sum(fn ($e) => (float) $e->amount), 2),
            'count' => $group->count(),
        ]);
    }

    private function parChart(int $months): array
    {
        // PAR: snapshot of overdue count vs active count per month-end (approximate using disbursement/maturity data)
        $start = now()->subMonths($months - 1)->startOfMonth();

        $rows = Loan::select('disbursement_date', 'status', 'outstanding_balance')
            ->active()
            ->whereNotNull('disbursement_date')
            ->where('disbursement_date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn ($l) => Carbon::parse($l->disbursement_date)->format('Y-m'));

        return $this->fillMonths($months, $rows, fn ($group) => [
            'total' => $group->count(),
            'overdue' => $group->filter(fn ($l) => $l->status === LoanStatus::Active)->count(),
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Build a full N-month series filling gaps with zeros.
     */
    private function fillMonths(int $months, $grouped, callable $reducer): array
    {
        $series = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $key = now()->subMonths($i)->format('Y-m');
            $label = now()->subMonths($i)->format('M Y');
            $group = $grouped->get($key);

            $series[] = array_merge(
                ['month' => $key, 'label' => $label],
                $group ? $reducer($group) : ['total' => 0, 'count' => 0],
            );
        }

        return $series;
    }
}
