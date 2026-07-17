<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Borrower;
use App\Models\Tenant\Loan;
use App\Models\Tenant\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $kpis = $this->buildKpis();
        $recentLoans = $this->recentLoans();
        $recentPayments = $this->recentPayments();
        $monthlyDisbursements = $this->monthlyDisbursements();

        return Inertia::render('dashboard/Index', compact(
            'kpis',
            'recentLoans',
            'recentPayments',
            'monthlyDisbursements',
        ));
    }

    private function buildKpis(): array
    {
        $activeLoans = Loan::active()->get();
        $overdueLoans = Loan::overdue()->get();
        $totalBorrowers = Borrower::where('is_active', true)->count();

        $totalOutstanding = $activeLoans->sum('outstanding_balance');
        $totalDisbursedMonth = Loan::whereMonth('disbursement_date', now()->month)
            ->whereYear('disbursement_date', now()->year)
            ->sum('principal_amount');
        $totalCollectedMonth = Payment::whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');

        $par = $activeLoans->count() > 0
            ? round(($overdueLoans->count() / $activeLoans->count()) * 100, 2)
            : 0;

        return [
            'active_loans'       => $activeLoans->count(),
            'total_borrowers'    => $totalBorrowers,
            'total_outstanding'  => number_format((float) $totalOutstanding, 2),
            'overdue_loans'      => $overdueLoans->count(),
            'disbursed_month'    => number_format((float) $totalDisbursedMonth, 2),
            'collected_month'    => number_format((float) $totalCollectedMonth, 2),
            'par_30'             => $par,
            'currency'           => tenancy()->tenant?->currency ?? 'ZMW',
        ];
    }

    private function recentLoans(): array
    {
        return Loan::with(['borrower:id,first_name,last_name,phone', 'loanType:id,name'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn ($loan) => [
                'id'          => $loan->id,
                'loan_number' => $loan->loan_number,
                'borrower'    => $loan->borrower->full_name,
                'type'        => $loan->loanType->name,
                'amount'      => number_format((float) $loan->principal_amount, 2),
                'status'      => $loan->status->value,
                'status_label' => $loan->status->label(),
                'status_color' => $loan->status->color(),
                'date'        => $loan->application_date->format('d M Y'),
            ])
            ->toArray();
    }

    private function recentPayments(): array
    {
        return Payment::with(['loan.borrower:id,first_name,last_name'])
            ->latest('payment_date')
            ->limit(8)
            ->get()
            ->map(fn ($p) => [
                'id'             => $p->id,
                'receipt_number' => $p->receipt_number,
                'borrower'       => $p->loan->borrower->full_name,
                'amount'         => number_format((float) $p->amount, 2),
                'method'         => $p->payment_method->label(),
                'date'           => $p->payment_date->format('d M Y'),
            ])
            ->toArray();
    }

    private function monthlyDisbursements(): array
    {
        return Loan::select(
            DB::raw('YEAR(disbursement_date) as year'),
            DB::raw('MONTH(disbursement_date) as month'),
            DB::raw('SUM(principal_amount) as total'),
            DB::raw('COUNT(*) as count')
        )
            ->whereNotNull('disbursement_date')
            ->where('disbursement_date', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($row) => [
                'label' => date('M Y', mktime(0, 0, 0, $row->month, 1, $row->year)),
                'total' => (float) $row->total,
                'count' => $row->count,
            ])
            ->toArray();
    }
}
