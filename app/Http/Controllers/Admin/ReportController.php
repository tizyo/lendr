<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Expense;
use App\Models\Tenant\ExpenseCategory;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\LoanType;
use App\Models\Tenant\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(): Response
    {
        $year = now()->year;

        // ── KPI stats ─────────────────────────────────────────────────────────
        $loanStats = Loan::query()
            ->selectRaw('
                COUNT(*)                                         AS total_loans,
                SUM(CASE WHEN status = \'active\'    THEN 1 ELSE 0 END) AS active_loans,
                SUM(CASE WHEN status = \'overdue\'   THEN 1 ELSE 0 END) AS overdue_loans,
                SUM(CASE WHEN status = \'completed\' THEN 1 ELSE 0 END) AS completed_loans,
                SUM(CASE WHEN status = \'defaulted\' THEN 1 ELSE 0 END) AS defaulted_loans,
                SUM(CASE WHEN status = \'submitted\' THEN 1 ELSE 0 END) AS pending_loans,
                COALESCE(SUM(principal_amount), 0)               AS total_disbursed,
                COALESCE(SUM(total_paid), 0)                     AS total_collected,
                COALESCE(SUM(outstanding_balance), 0)            AS total_outstanding
            ')
            ->whereNotIn('status', ['draft', 'submitted'])
            ->first();

        $totalBorrowers  = Borrower::count();
        $activeBorrowers = Borrower::whereHas('loans', fn ($q) => $q->where('status', 'active'))->count();

        $nplRate = $loanStats->total_loans > 0
            ? round(($loanStats->overdue_loans + $loanStats->defaulted_loans) / $loanStats->total_loans * 100, 1)
            : 0;

        $stats = [
            'total_loans'        => (int) $loanStats->total_loans,
            'active_loans'       => (int) $loanStats->active_loans,
            'overdue_loans'      => (int) $loanStats->overdue_loans,
            'completed_loans'    => (int) $loanStats->completed_loans,
            'defaulted_loans'    => (int) $loanStats->defaulted_loans,
            'pending_loans'      => (int) $loanStats->pending_loans,
            'total_disbursed'    => round((float) $loanStats->total_disbursed, 2),
            'total_collected'    => round((float) $loanStats->total_collected, 2),
            'total_outstanding'  => round((float) $loanStats->total_outstanding, 2),
            'total_borrowers'    => $totalBorrowers,
            'active_borrowers'   => $activeBorrowers,
            'npl_rate'           => $nplRate,
        ];

        // ── Monthly disbursements (current year) ──────────────────────────────
        $monthlyDisbursements = Loan::query()
            ->selectRaw('MONTH(disbursement_date) AS month, COUNT(*) AS count, COALESCE(SUM(principal_amount), 0) AS amount')
            ->whereYear('disbursement_date', $year)
            ->whereNotNull('disbursement_date')
            ->groupByRaw('MONTH(disbursement_date)')
            ->orderByRaw('MONTH(disbursement_date)')
            ->get()
            ->keyBy('month');

        // ── Monthly collections (current year) ───────────────────────────────
        $monthlyCollections = Payment::query()
            ->selectRaw('MONTH(payment_date) AS month, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS amount')
            ->whereYear('payment_date', $year)
            ->groupByRaw('MONTH(payment_date)')
            ->orderByRaw('MONTH(payment_date)')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $months[] = [
                'month'           => $m,
                'label'           => date('M', mktime(0, 0, 0, $m, 1)),
                'disbursed_count' => (int) ($monthlyDisbursements[$m]->count ?? 0),
                'disbursed_amount'=> round((float) ($monthlyDisbursements[$m]->amount ?? 0), 2),
                'collected_count' => (int) ($monthlyCollections[$m]->count ?? 0),
                'collected_amount'=> round((float) ($monthlyCollections[$m]->amount ?? 0), 2),
            ];
        }

        // ── Loan type breakdown ───────────────────────────────────────────────
        $loanTypeBreakdown = Loan::query()
            ->selectRaw('loan_type_id, COUNT(*) AS count, COALESCE(SUM(principal_amount), 0) AS amount')
            ->with('loanType:id,name')
            ->whereNotIn('status', ['draft'])
            ->groupBy('loan_type_id')
            ->orderByRaw('count DESC')
            ->get()
            ->map(fn ($r) => [
                'name'   => $r->loanType?->name ?? 'Unknown',
                'count'  => (int) $r->count,
                'amount' => round((float) $r->amount, 2),
            ]);

        // ── Status breakdown ──────────────────────────────────────────────────
        $statusBreakdown = Loan::query()
            ->selectRaw('status, COUNT(*) AS count')
            ->groupBy('status')
            ->get()
            ->map(fn ($r) => [
                'status' => $r->status instanceof \BackedEnum ? $r->status->value : $r->status,
                'count'  => (int) $r->count,
            ]);

        // ── Recent payments ───────────────────────────────────────────────────
        $recentPayments = Payment::query()
            ->with('loan:id,loan_number', 'loan.borrower:id,first_name,last_name')
            ->latest('payment_date')
            ->limit(10)
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'receipt_number' => $p->receipt_number,
                'loan_number'    => $p->loan?->loan_number,
                'borrower'       => $p->loan?->borrower
                    ? trim($p->loan->borrower->first_name . ' ' . $p->loan->borrower->last_name)
                    : null,
                'amount'         => round((float) $p->amount, 2),
                'payment_date'   => $p->payment_date?->toDateString(),
                'payment_method' => $p->payment_method instanceof \BackedEnum
                    ? $p->payment_method->value
                    : $p->payment_method,
            ]);

        return Inertia::render('reports/Index', [
            'year'              => $year,
            'stats'             => $stats,
            'months'            => $months,
            'loanTypeBreakdown' => $loanTypeBreakdown,
            'statusBreakdown'   => $statusBreakdown,
            'recentPayments'    => $recentPayments,
            'loanTypes'         => LoanType::orderBy('name')->get(['id', 'name']),
            'categories'        => ExpenseCategory::active()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // ─── Phase 25: Advanced Reports ───────────────────────────────────────────

    /**
     * GET /reports/par — Portfolio At Risk (PAR) aging report
     */
    public function par(Request $request): Response
    {
        $asOf = $request->date ? \Carbon\Carbon::parse($request->date) : now();

        $overdueSchedules = LoanSchedule::query()
            ->with('loan:id,loan_number,borrower_id,principal_amount,outstanding_balance,total_payable',
                   'loan.borrower:id,first_name,last_name,borrower_number')
            ->where('is_paid', false)
            ->where('days_overdue', '>', 0)
            ->orderByDesc('days_overdue')
            ->get();

        // Group by borrower+loan, keep worst bucket per loan
        $loans = [];
        foreach ($overdueSchedules as $s) {
            $key = $s->loan_id;
            if (!isset($loans[$key]) || $s->days_overdue > $loans[$key]['days_overdue']) {
                $loans[$key] = [
                    'loan_number'      => $s->loan?->loan_number,
                    'borrower_name'    => $s->loan?->borrower?->full_name,
                    'borrower_number'  => $s->loan?->borrower?->borrower_number,
                    'principal'        => (float) $s->loan?->principal_amount,
                    'outstanding'      => (float) $s->loan?->outstanding_balance,
                    'outstanding_inst' => (float) $s->outstanding,
                    'days_overdue'     => (int) $s->days_overdue,
                ];
            }
        }

        $buckets = ['1_30' => [], '31_60' => [], '61_90' => [], '91_plus' => []];
        foreach ($loans as $loan) {
            $d = $loan['days_overdue'];
            if      ($d <= 30) $buckets['1_30'][]    = $loan;
            elseif  ($d <= 60) $buckets['31_60'][]   = $loan;
            elseif  ($d <= 90) $buckets['61_90'][]   = $loan;
            else               $buckets['91_plus'][] = $loan;
        }

        $totalPortfolio = Loan::whereNotIn('status', ['draft', 'submitted', 'denied', 'completed'])->sum('outstanding_balance');

        $summary = [];
        foreach ($buckets as $key => $rows) {
            $outstanding = array_sum(array_column($rows, 'outstanding'));
            $summary[$key] = [
                'count'       => count($rows),
                'outstanding' => round($outstanding, 2),
                'par_rate'    => $totalPortfolio > 0 ? round($outstanding / $totalPortfolio * 100, 2) : 0,
            ];
        }

        return Inertia::render('reports/Par', [
            'asOf'           => $asOf->format('d M Y'),
            'buckets'        => $buckets,
            'summary'        => $summary,
            'totalPortfolio' => round((float) $totalPortfolio, 2),
        ]);
    }

    /**
     * GET /reports/officer — Loan Officer Performance
     */
    public function loanOfficer(Request $request): Response
    {
        $from = $request->date_from ? \Carbon\Carbon::parse($request->date_from)->startOfDay() : now()->startOfYear();
        $to   = $request->date_to   ? \Carbon\Carbon::parse($request->date_to)->endOfDay()   : now()->endOfDay();

        $officers = DB::table('loans')
            ->join('users', 'loans.created_by', '=', 'users.id')
            ->select('users.id', 'users.name',
                DB::raw('COUNT(*) as total_loans'),
                DB::raw("SUM(CASE WHEN loans.status NOT IN ('draft','submitted') THEN 1 ELSE 0 END) as disbursed_loans"),
                DB::raw("SUM(CASE WHEN loans.status = 'completed' THEN 1 ELSE 0 END) as completed_loans"),
                DB::raw("SUM(CASE WHEN loans.status IN ('defaulted','written_off') THEN 1 ELSE 0 END) as defaulted_loans"),
                DB::raw('COALESCE(SUM(CASE WHEN loans.status NOT IN (\'draft\',\'submitted\') THEN loans.principal_amount ELSE 0 END), 0) as total_disbursed'),
                DB::raw('COALESCE(SUM(loans.total_paid), 0) as total_collected')
            )
            ->whereBetween('loans.application_date', [$from, $to])
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_loans')
            ->get()
            ->map(fn ($r) => [
                'officer_name'    => $r->name,
                'total_loans'     => (int) $r->total_loans,
                'disbursed_loans' => (int) $r->disbursed_loans,
                'completed_loans' => (int) $r->completed_loans,
                'defaulted_loans' => (int) $r->defaulted_loans,
                'total_disbursed' => round((float) $r->total_disbursed, 2),
                'total_collected' => round((float) $r->total_collected, 2),
                'default_rate'    => $r->disbursed_loans > 0
                    ? round($r->defaulted_loans / $r->disbursed_loans * 100, 1)
                    : 0,
                'collection_rate' => $r->total_disbursed > 0
                    ? round($r->total_collected / $r->total_disbursed * 100, 1)
                    : 0,
            ]);

        return Inertia::render('reports/Officer', [
            'officers'  => $officers,
            'dateFrom'  => $from->format('d M Y'),
            'dateTo'    => $to->format('d M Y'),
            'filters'   => $request->only(['date_from', 'date_to']),
        ]);
    }

    /**
     * GET /reports/collections — Collections Efficiency
     */
    public function collections(Request $request): Response
    {
        $year = $request->year ? (int) $request->year : now()->year;

        // Monthly collections vs expected (instalments due)
        $monthlyCollections = Payment::query()
            ->selectRaw('MONTH(payment_date) AS month, COUNT(*) AS count, COALESCE(SUM(amount), 0) AS collected')
            ->whereYear('payment_date', $year)
            ->groupByRaw('MONTH(payment_date)')
            ->get()
            ->keyBy('month');

        $monthlyDue = LoanSchedule::query()
            ->selectRaw('MONTH(due_date) AS month, COUNT(*) AS count, COALESCE(SUM(total_due), 0) AS due')
            ->whereYear('due_date', $year)
            ->groupByRaw('MONTH(due_date)')
            ->get()
            ->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $due       = (float) ($monthlyDue[$m]->due ?? 0);
            $collected = (float) ($monthlyCollections[$m]->collected ?? 0);
            $months[] = [
                'month'           => $m,
                'label'           => date('M', mktime(0, 0, 0, $m, 1)),
                'due'             => round($due, 2),
                'collected'       => round($collected, 2),
                'efficiency_rate' => $due > 0 ? round($collected / $due * 100, 1) : 0,
                'instalments_due' => (int) ($monthlyDue[$m]->count ?? 0),
                'payments_count'  => (int) ($monthlyCollections[$m]->count ?? 0),
            ];
        }

        // Top collectors (by payment count)
        $topCollectors = DB::table('payments')
            ->join('users', 'payments.recorded_by', '=', 'users.id')
            ->select('users.name',
                DB::raw('COUNT(*) as payment_count'),
                DB::raw('COALESCE(SUM(payments.amount), 0) as total_collected')
            )
            ->whereYear('payments.payment_date', $year)
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total_collected')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'name'            => $r->name,
                'payment_count'   => (int) $r->payment_count,
                'total_collected' => round((float) $r->total_collected, 2),
            ]);

        return Inertia::render('reports/Collections', [
            'year'          => $year,
            'months'        => $months,
            'topCollectors' => $topCollectors,
            'filters'       => ['year' => $year],
        ]);
    }

    /**
     * GET /reports/pnl — Profit & Loss Summary
     */
    public function pnl(Request $request): Response
    {
        $year  = $request->year  ? (int) $request->year  : now()->year;
        $month = $request->month ? (int) $request->month : null;

        $paymentQuery = Payment::query()->whereYear('payment_date', $year);
        $expenseQuery = Expense::query()->where('status', 'approved')->whereYear('expense_date', $year);

        if ($month) {
            $paymentQuery->whereMonth('payment_date', $month);
            $expenseQuery->whereMonth('expense_date', $month);
        }

        // Revenue breakdown: interest + penalty collected
        $revenueData = $paymentQuery->selectRaw(
            'MONTH(payment_date) AS month,
             COALESCE(SUM(interest_allocated), 0) AS interest_income,
             COALESCE(SUM(penalty_allocated), 0)  AS penalty_income,
             COALESCE(SUM(amount), 0)             AS total_collected'
        )->groupByRaw('MONTH(payment_date)')->get()->keyBy('month');

        // New disbursements (principal out)
        $disbursements = Loan::query()
            ->whereYear('disbursement_date', $year)
            ->when($month, fn ($q) => $q->whereMonth('disbursement_date', $month))
            ->whereNotNull('disbursement_date')
            ->selectRaw('MONTH(disbursement_date) AS month, COALESCE(SUM(principal_amount), 0) AS disbursed')
            ->groupByRaw('MONTH(disbursement_date)')
            ->get()->keyBy('month');

        // Processing + insurance fee income (from new loans)
        $feeIncome = Loan::query()
            ->whereYear('disbursement_date', $year)
            ->when($month, fn ($q) => $q->whereMonth('disbursement_date', $month))
            ->whereNotNull('disbursement_date')
            ->selectRaw('MONTH(disbursement_date) AS month,
                COALESCE(SUM(processing_fee), 0) AS processing_fees,
                COALESCE(SUM(insurance_fee), 0)  AS insurance_fees')
            ->groupByRaw('MONTH(disbursement_date)')
            ->get()->keyBy('month');

        // Expenses by month
        $expensesByMonth = $expenseQuery->selectRaw(
            'MONTH(expense_date) AS month, COALESCE(SUM(amount), 0) AS total_expenses'
        )->groupByRaw('MONTH(expense_date)')->get()->keyBy('month');

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            if ($month && $m !== $month) continue;
            $interestIncome  = (float) ($revenueData[$m]->interest_income ?? 0);
            $penaltyIncome   = (float) ($revenueData[$m]->penalty_income ?? 0);
            $processingFees  = (float) ($feeIncome[$m]->processing_fees ?? 0);
            $insuranceFees   = (float) ($feeIncome[$m]->insurance_fees ?? 0);
            $totalRevenue    = $interestIncome + $penaltyIncome + $processingFees + $insuranceFees;
            $totalExpenses   = (float) ($expensesByMonth[$m]->total_expenses ?? 0);
            $netProfit       = $totalRevenue - $totalExpenses;

            $months[] = [
                'month'           => $m,
                'label'           => date('M', mktime(0, 0, 0, $m, 1)),
                'interest_income' => round($interestIncome, 2),
                'penalty_income'  => round($penaltyIncome, 2),
                'processing_fees' => round($processingFees, 2),
                'insurance_fees'  => round($insuranceFees, 2),
                'total_revenue'   => round($totalRevenue, 2),
                'total_expenses'  => round($totalExpenses, 2),
                'net_profit'      => round($netProfit, 2),
                'disbursed'       => round((float) ($disbursements[$m]->disbursed ?? 0), 2),
            ];
        }

        $totals = [
            'interest_income' => round(array_sum(array_column($months, 'interest_income')), 2),
            'penalty_income'  => round(array_sum(array_column($months, 'penalty_income')), 2),
            'processing_fees' => round(array_sum(array_column($months, 'processing_fees')), 2),
            'insurance_fees'  => round(array_sum(array_column($months, 'insurance_fees')), 2),
            'total_revenue'   => round(array_sum(array_column($months, 'total_revenue')), 2),
            'total_expenses'  => round(array_sum(array_column($months, 'total_expenses')), 2),
            'net_profit'      => round(array_sum(array_column($months, 'net_profit')), 2),
            'disbursed'       => round(array_sum(array_column($months, 'disbursed')), 2),
        ];

        return Inertia::render('reports/Pnl', [
            'year'    => $year,
            'month'   => $month,
            'months'  => $months,
            'totals'  => $totals,
            'filters' => ['year' => $year, 'month' => $month],
        ]);
    }
}
