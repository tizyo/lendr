<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\ReportExport;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Loan;
use App\Models\Tenant\LoanSchedule;
use App\Models\Tenant\Payment;
use App\Models\Tenant\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends BaseApiController
{
    /**
     * GET /api/v1/reports/{type}
     * Types: loans | payments | expenses | borrowers
     * Returns JSON data ready for client-side rendering or CSV export.
     */
    public function generate(Request $request, string $type): JsonResponse
    {
        $data = match ($type) {
            'loans' => $this->loansReport($request),
            'payments' => $this->paymentsReport($request),
            'expenses' => $this->expensesReport($request),
            'borrowers' => $this->borrowersReport($request),
            'par' => $this->parReport($request),
            'collection' => $this->collectionReport($request),
            'portfolio_trend' => $this->portfolioTrendReport($request),
            'demographics' => $this->demographicsReport($request),
            'cohort' => $this->cohortReport($request),
            'officer_league' => $this->officerLeagueReport($request),
            'geographic' => $this->geographicReport($request),
            default => null,
        };

        if ($data === null) {
            return $this->error("Unknown report type '{$type}'. Valid: loans, payments, expenses, borrowers, par, collection.", 422);
        }

        return $this->success($data);
    }

    /**
     * GET /api/v1/reports/{type}/export?format=excel|csv|pdf
     * Streams a file download for the given report type and format.
     */
    public function export(Request $request, string $type): Response|BinaryFileResponse|JsonResponse
    {
        $format = strtolower($request->query('format', 'excel'));

        $data = match ($type) {
            'loans' => $this->loansReport($request),
            'payments' => $this->paymentsReport($request),
            'expenses' => $this->expensesReport($request),
            'borrowers' => $this->borrowersReport($request),
            'par' => $this->parReport($request),
            'collection' => $this->collectionReport($request),
            'portfolio_trend' => $this->portfolioTrendReport($request),
            'officer_league' => $this->officerLeagueReport($request),
            default => null,
        };

        if ($data === null) {
            return $this->error("Unknown report type '{$type}'.", 422);
        }

        // Report methods can return either a plain array or an Eloquent
        // Collection for 'rows' - normalize to a plain array so empty()
        // and index access below behave the same either way (empty() is
        // always false on an object, so an empty Collection would
        // otherwise be treated as non-empty and $rows[0] would fail).
        $rows = collect($data['rows'] ?? [])->toArray();
        $filename = "{$type}-report-".now()->format('Y-m-d');

        if ($format === 'csv') {
            return $this->streamCsv($rows, $filename);
        }

        if ($format === 'pdf') {
            return $this->streamPdf($type, $data, $filename);
        }

        // Default: Excel
        $headings = ! empty($rows) ? array_map(
            fn ($k) => ucwords(str_replace('_', ' ', $k)),
            array_keys((array) $rows[0]),
        ) : [];

        $rowArrays = array_map(fn ($row) => array_values((array) $row), $rows);

        return Excel::download(
            new ReportExport($rowArrays, $headings, ucwords(str_replace('_', '-', $type))),
            "{$filename}.xlsx",
        );
    }

    private function streamCsv(array $rows, string $filename): Response
    {
        if (empty($rows)) {
            return response('No data', 204);
        }

        $headings = array_keys((array) $rows[0]);
        $lines = [implode(',', array_map(fn ($h) => '"'.str_replace('"', '""', $h).'"', $headings))];

        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) ($v ?? '')).'"',
                array_values((array) $row),
            ));
        }

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }

    private function streamPdf(string $type, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView('pdf.reports.generic', [
            'type' => ucwords(str_replace('_', ' ', $type)),
            'data' => $data,
            'rows' => $data['rows'] ?? [],
            'summary' => $data['summary'] ?? [],
            'generated_at' => now()->format('d M Y H:i'),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("{$filename}.pdf");
    }

    // ─── Report builders ─────────────────────────────────────────────────────

    private function loansReport(Request $request): array
    {
        $query = Loan::query()
            ->with([
                'borrower:id,first_name,last_name,borrower_number,phone',
                'loanType:id,name',
                'loanPlan:id,name',
            ])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->date_from, fn ($q, $d) => $q->where('application_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('application_date', '<=', $d))
            ->latest('application_date')
            ->limit(2000);

        $loans = $query->get()->map(fn ($l) => [
            'loan_number' => $l->loan_number,
            'borrower_number' => $l->borrower->borrower_number,
            'borrower_name' => $l->borrower->full_name,
            'borrower_phone' => $l->borrower->phone,
            'loan_type' => $l->loanType->name,
            'loan_plan' => $l->loanPlan->name,
            'principal_amount' => (float) $l->principal_amount,
            'total_repayable' => (float) $l->total_repayable,
            'outstanding_balance' => (float) $l->outstanding_balance,
            'status' => $l->status->value,
            'application_date' => $l->application_date?->toDateString(),
            'disbursement_date' => $l->disbursement_date?->toDateString(),
            'maturity_date' => $l->maturity_date?->toDateString(),
        ]);

        return [
            'type' => 'loans',
            'total' => $loans->count(),
            'summary' => [
                'total_principal' => round($loans->sum('principal_amount'), 2),
                'total_outstanding' => round($loans->sum('outstanding_balance'), 2),
            ],
            'rows' => $loans->values(),
        ];
    }

    private function paymentsReport(Request $request): array
    {
        $payments = Payment::query()
            ->with(['loan.borrower:id,first_name,last_name,borrower_number'])
            ->when($request->date_from, fn ($q, $d) => $q->where('payment_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('payment_date', '<=', $d))
            ->when($request->loan_id, fn ($q, $id) => $q->where('loan_id', $id))
            ->latest('payment_date')
            ->limit(2000)
            ->get()
            ->map(fn ($p) => [
                'receipt_number' => $p->receipt_number,
                'loan_number' => $p->loan->loan_number,
                'borrower_number' => $p->loan->borrower->borrower_number,
                'borrower_name' => $p->loan->borrower->full_name,
                'amount' => (float) $p->amount,
                'principal_allocated' => (float) $p->principal_allocated,
                'interest_allocated' => (float) $p->interest_allocated,
                'penalty_allocated' => (float) $p->penalty_allocated,
                'payment_method' => $p->payment_method->value,
                'payment_date' => $p->payment_date->toDateString(),
            ]);

        return [
            'type' => 'payments',
            'total' => $payments->count(),
            'summary' => [
                'total_collected' => round($payments->sum('amount'), 2),
                'total_principal' => round($payments->sum('principal_allocated'), 2),
                'total_interest' => round($payments->sum('interest_allocated'), 2),
                'total_penalties' => round($payments->sum('penalty_allocated'), 2),
            ],
            'rows' => $payments->values(),
        ];
    }

    private function expensesReport(Request $request): array
    {
        $expenses = Expense::query()
            ->with(['category:id,name,code', 'submittedBy:id,name'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->date_from, fn ($q, $d) => $q->where('expense_date', '>=', $d))
            ->when($request->date_to, fn ($q, $d) => $q->where('expense_date', '<=', $d))
            ->latest('expense_date')
            ->limit(2000)
            ->get()
            ->map(fn ($e) => [
                'expense_number' => $e->expense_number,
                'title' => $e->title,
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'currency' => $e->currency,
                'vendor' => $e->vendor,
                'status' => $e->status->value,
                'expense_date' => $e->expense_date?->toDateString(),
                'submitted_by' => $e->submittedBy?->name,
            ]);

        return [
            'type' => 'expenses',
            'total' => $expenses->count(),
            'summary' => [
                'total_amount' => round($expenses->sum('amount'), 2),
                'total_approved' => round(
                    $expenses->where('status', 'approved')->sum('amount'), 2,
                ),
            ],
            'rows' => $expenses->values(),
        ];
    }

    private function borrowersReport(Request $request): array
    {
        $borrowers = Borrower::query()
            ->withCount([
                'loans',
                'loans as active_loans_count' => fn ($q) => $q->whereIn('status', ['disbursed', 'active']),
            ])
            ->when($request->query('is_active') !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('created_at')
            ->limit(2000)
            ->get()
            ->map(fn ($b) => [
                'borrower_number' => $b->borrower_number,
                'first_name' => $b->first_name,
                'last_name' => $b->last_name,
                'phone' => $b->phone,
                'email' => $b->email,
                'national_id' => $b->national_id,
                'is_active' => $b->is_active,
                'total_loans' => $b->loans_count,
                'active_loans' => $b->active_loans_count,
                'date_registered' => $b->created_at->toDateString(),
            ]);

        return [
            'type' => 'borrowers',
            'total' => $borrowers->count(),
            'summary' => [
                'active' => $borrowers->where('is_active', true)->count(),
                'inactive' => $borrowers->where('is_active', false)->count(),
            ],
            'rows' => $borrowers->values(),
        ];
    }

    /**
     * Portfolio-at-Risk (PAR) report.
     * Groups overdue loans by days-past-due buckets: PAR1, PAR7, PAR30, PAR90.
     */
    private function parReport(Request $request): array
    {
        $activeStatuses = ['disbursed', 'active', 'overdue'];

        $loans = Loan::query()
            ->with(['borrower:id,first_name,last_name,borrower_number', 'loanType:id,name'])
            ->whereIn('status', $activeStatuses)
            ->get();

        $today = now()->startOfDay();
        $totalPortfolio = $loans->sum(fn ($l) => (float) $l->outstanding_balance);

        $buckets = [
            'current' => collect(),
            'par1' => collect(), // 1–6 days
            'par7' => collect(), // 7–29 days
            'par30' => collect(), // 30–89 days
            'par90' => collect(), // 90+ days
        ];

        foreach ($loans as $loan) {
            // Find the earliest unpaid or partially-paid overdue installment
            $firstOverdue = LoanSchedule::where('loan_id', $loan->id)
                ->where('due_date', '<', $today)
                ->whereColumn('amount_paid', '<', 'amount_due')
                ->orderBy('due_date')
                ->first();

            if (! $firstOverdue) {
                $buckets['current']->push($loan);

                continue;
            }

            $dpd = (int) $today->diffInDays($firstOverdue->due_date);

            if ($dpd < 1) {
                $buckets['current']->push($loan);
            } elseif ($dpd < 7) {
                $buckets['par1']->push($loan);
            } elseif ($dpd < 30) {
                $buckets['par7']->push($loan);
            } elseif ($dpd < 90) {
                $buckets['par30']->push($loan);
            } else {
                $buckets['par90']->push($loan);
            }
        }

        $formatBucket = fn ($bucket) => [
            'count' => $bucket->count(),
            'outstanding' => round($bucket->sum(fn ($l) => (float) $l->outstanding_balance), 2),
            'par_ratio' => $totalPortfolio > 0
                ? round($bucket->sum(fn ($l) => (float) $l->outstanding_balance) / $totalPortfolio * 100, 2)
                : 0,
        ];

        $rows = $loans->whereIn('id', collect($buckets)->flatten()->pluck('id'))
            ->map(fn ($l) => [
                'loan_number' => $l->loan_number,
                'borrower_number' => $l->borrower->borrower_number,
                'borrower_name' => $l->borrower->full_name,
                'loan_type' => $l->loanType->name,
                'outstanding_balance' => (float) $l->outstanding_balance,
                'status' => $l->status->value,
                'disbursement_date' => $l->disbursement_date?->toDateString(),
                'maturity_date' => $l->maturity_date?->toDateString(),
            ]);

        return [
            'type' => 'par',
            'total' => $loans->count(),
            'summary' => [
                'total_portfolio' => round($totalPortfolio, 2),
                'par_buckets' => [
                    'current' => $formatBucket($buckets['current']),
                    'par1' => $formatBucket($buckets['par1']),
                    'par7' => $formatBucket($buckets['par7']),
                    'par30' => $formatBucket($buckets['par30']),
                    'par90' => $formatBucket($buckets['par90']),
                ],
            ],
            'rows' => $rows->values(),
        ];
    }

    /**
     * Collection efficiency report.
     * Compares scheduled repayments vs. actual collections per month.
     */
    private function collectionReport(Request $request): array
    {
        $year = (int) ($request->year ?? now()->year);

        // Group in PHP to stay DB-agnostic (SQLite / MySQL)
        $scheduled = LoanSchedule::whereYear('due_date', $year)
            ->get(['due_date', 'amount_due'])
            ->groupBy(fn ($r) => (int) $r->due_date->format('n'))
            ->map(fn ($rows) => $rows->sum(fn ($r) => (float) $r->amount_due));

        $collected = Payment::whereYear('payment_date', $year)
            ->get(['payment_date', 'amount'])
            ->groupBy(fn ($r) => (int) $r->payment_date->format('n'))
            ->map(fn ($rows) => $rows->sum(fn ($r) => (float) $r->amount));

        $months = [];
        for ($m = 1; $m <= 12; $m++) {
            $due = (float) ($scheduled[$m] ?? 0);
            $collected_ = (float) ($collected[$m] ?? 0);
            $months[] = [
                'month' => $m,
                'label' => now()->setMonth($m)->format('M'),
                'total_due' => round($due, 2),
                'total_collected' => round($collected_, 2),
                'efficiency' => $due > 0 ? round($collected_ / $due * 100, 2) : null,
            ];
        }

        $totalDue = array_sum(array_column($months, 'total_due'));
        $totalCollected = array_sum(array_column($months, 'total_collected'));

        return [
            'type' => 'collection',
            'year' => $year,
            'summary' => [
                'total_due' => round($totalDue, 2),
                'total_collected' => round($totalCollected, 2),
                'overall_efficiency' => $totalDue > 0 ? round($totalCollected / $totalDue * 100, 2) : null,
            ],
            'rows' => $months,
        ];
    }

    // ─── Phase 40: Advanced Analytics ────────────────────────────────────────

    /**
     * Portfolio trend: monthly disbursements & outstanding over the last N months.
     */
    private function portfolioTrendReport(Request $request): array
    {
        $months = (int) ($request->months ?? 12);
        $months = max(1, min(36, $months));

        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $label = $date->format('M Y');
            $month = $date->month;
            $year = $date->year;

            $disbursed = Loan::whereYear('disbursement_date', $year)
                ->whereMonth('disbursement_date', $month)
                ->sum('principal_amount');

            $outstanding = Loan::whereIn('status', ['disbursed', 'active', 'overdue'])
                ->whereYear('disbursement_date', '<=', $year)
                ->sum('outstanding_balance');

            $newBorrowers = Borrower::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->count();

            $rows[] = [
                'month' => $label,
                'disbursed' => round((float) $disbursed, 2),
                'outstanding' => round((float) $outstanding, 2),
                'new_borrowers' => $newBorrowers,
            ];
        }

        return [
            'type' => 'portfolio_trend',
            'rows' => $rows,
        ];
    }

    /**
     * Demographics: borrower breakdown by gender, city, occupation.
     */
    private function demographicsReport(Request $request): array
    {
        $byGender = Borrower::query()
            ->selectRaw('gender, COUNT(*) as count')
            ->whereNotNull('gender')
            ->groupBy('gender')
            ->pluck('count', 'gender')
            ->toArray();

        $byCity = Borrower::query()
            ->selectRaw('city, COUNT(*) as count')
            ->whereNotNull('city')
            ->groupBy('city')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['city' => $r->city, 'count' => (int) $r->count])
            ->values();

        $byOccupation = Borrower::query()
            ->selectRaw('occupation, COUNT(*) as count')
            ->whereNotNull('occupation')
            ->groupBy('occupation')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(10)
            ->get()
            ->map(fn ($r) => ['occupation' => $r->occupation, 'count' => (int) $r->count])
            ->values();

        $totalBorrowers = Borrower::count();

        return [
            'type' => 'demographics',
            'total_borrowers' => $totalBorrowers,
            'by_gender' => $byGender,
            'by_city' => $byCity,
            'by_occupation' => $byOccupation,
        ];
    }

    /**
     * Cohort analysis: repayment rate by loan origination month.
     */
    private function cohortReport(Request $request): array
    {
        $months = (int) ($request->months ?? 12);
        $months = max(1, min(24, $months));

        $rows = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->startOfMonth()->subMonths($i);
            $label = $date->format('M Y');

            $loans = Loan::whereYear('disbursement_date', $date->year)
                ->whereMonth('disbursement_date', $date->month)
                ->whereNotNull('disbursement_date')
                ->get(['id', 'principal_amount', 'total_paid', 'outstanding_balance', 'status']);

            $count = $loans->count();
            $disbursed = $loans->sum(fn ($l) => (float) $l->principal_amount);
            $collected = $loans->sum(fn ($l) => (float) $l->total_paid);
            $completed = $loans->where('status', \App\Enums\LoanStatus::Completed)->count();
            $defaulted = $loans->whereIn('status', [
                \App\Enums\LoanStatus::Defaulted,
                \App\Enums\LoanStatus::WrittenOff,
            ])->count();

            $rows[] = [
                'cohort' => $label,
                'loan_count' => $count,
                'total_disbursed' => round($disbursed, 2),
                'total_collected' => round($collected, 2),
                'collection_rate' => $disbursed > 0 ? round($collected / $disbursed * 100, 2) : null,
                'completed_count' => $completed,
                'defaulted_count' => $defaulted,
            ];
        }

        return [
            'type' => 'cohort',
            'rows' => $rows,
        ];
    }

    /**
     * Officer league table: performance ranking by loan officer.
     */
    private function officerLeagueReport(Request $request): array
    {
        $dateFrom = $request->date_from ?? now()->startOfMonth()->toDateString();
        $dateTo = $request->date_to ?? now()->toDateString();

        $officers = User::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'role']);

        $rows = $officers->map(function ($officer) use ($dateFrom, $dateTo) {
            $loans = Loan::where('created_by', $officer->id)
                ->whereBetween('application_date', [$dateFrom, $dateTo])
                ->get(['id', 'principal_amount', 'status', 'disbursement_date']);

            $disbursed = $loans->whereNotNull('disbursement_date')->sum(fn ($l) => (float) $l->principal_amount);

            $collected = Payment::where('recorded_by', $officer->id)
                ->whereBetween('payment_date', [$dateFrom, $dateTo])
                ->sum('amount');

            return [
                'officer_id' => $officer->id,
                'officer_name' => $officer->name,
                'role' => $officer->role,
                'loans_created' => $loans->count(),
                'loans_disbursed' => $loans->whereNotNull('disbursement_date')->count(),
                'amount_disbursed' => round($disbursed, 2),
                'amount_collected' => round((float) $collected, 2),
            ];
        })
            ->sortByDesc('amount_disbursed')
            ->values();

        return [
            'type' => 'officer_league',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'rows' => $rows,
        ];
    }

    /**
     * Geographic distribution: active loans and outstanding by city/region.
     */
    private function geographicReport(Request $request): array
    {
        $loansByCity = Loan::query()
            ->whereIn('status', ['disbursed', 'active', 'overdue'])
            ->join('borrowers', 'loans.borrower_id', '=', 'borrowers.id')
            ->selectRaw('borrowers.city, COUNT(loans.id) as loan_count, SUM(loans.outstanding_balance) as outstanding, SUM(loans.principal_amount) as disbursed')
            ->whereNotNull('borrowers.city')
            ->groupBy('borrowers.city')
            ->orderByRaw('COUNT(loans.id) DESC')
            ->limit(20)
            ->get()
            ->map(fn ($r) => [
                'city' => $r->city,
                'loan_count' => (int) $r->loan_count,
                'outstanding' => round((float) $r->outstanding, 2),
                'disbursed' => round((float) $r->disbursed, 2),
            ])
            ->values();

        return [
            'type' => 'geographic',
            'rows' => $loansByCity,
        ];
    }
}
