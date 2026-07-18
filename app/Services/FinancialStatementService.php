<?php

namespace App\Services;

use App\Models\Tenant\GlAccount;
use App\Models\Tenant\Loan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinancialStatementService
{
    /**
     * Balance Sheet as of a given date.
     */
    public function balanceSheet(?string $asOf = null): array
    {
        $date = $asOf ? Carbon::parse($asOf)->toDateString() : now()->toDateString();

        // Aggregate debits and credits per account up to the given date
        $agg = DB::table('gl_journal_lines as l')
            ->join('gl_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->join('gl_accounts as a', 'l.account_id', '=', 'a.id')
            ->whereDate('e.entry_date', '<=', $date)
            ->selectRaw('l.account_id, l.side, SUM(l.amount) as total')
            ->groupBy('l.account_id', 'l.side')
            ->get()
            ->groupBy('account_id')
            ->map(fn ($rows) => $rows->keyBy('side'));

        $accounts = GlAccount::where('is_active', true)->orderBy('code')->get();

        $assets = $liabilities = $equity = [];

        foreach ($accounts as $account) {
            $rows = $agg->get($account->id, collect());
            $debits = (float) ($rows->get('debit')->total ?? 0);
            $credits = (float) ($rows->get('credit')->total ?? 0);

            $balance = match ($account->type) {
                'asset', 'expense' => $debits - $credits,
                'liability', 'equity', 'income' => $credits - $debits,
            };

            $row = ['code' => $account->code, 'name' => $account->name, 'balance' => round($balance, 2)];

            match ($account->type) {
                'asset' => $assets[] = $row,
                'liability' => $liabilities[] = $row,
                'equity' => $equity[] = $row,
                default => null,
            };
        }

        $totalAssets = round(array_sum(array_column($assets, 'balance')), 2);
        $totalLiabilities = round(array_sum(array_column($liabilities, 'balance')), 2);
        $totalEquity = round(array_sum(array_column($equity, 'balance')), 2);

        return [
            'as_of' => $date,
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'net_assets' => round($totalAssets - $totalLiabilities, 2),
        ];
    }

    /**
     * Income Statement for a date range.
     */
    public function incomeStatement(?string $from = null, ?string $to = null): array
    {
        $from = $from ? Carbon::parse($from)->toDateString() : now()->startOfMonth()->toDateString();
        $to = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();

        $agg = DB::table('gl_journal_lines as l')
            ->join('gl_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->join('gl_accounts as a', 'l.account_id', '=', 'a.id')
            ->whereDate('e.entry_date', '>=', $from)
            ->whereDate('e.entry_date', '<=', $to)
            ->selectRaw('l.account_id, l.side, SUM(l.amount) as total')
            ->groupBy('l.account_id', 'l.side')
            ->get()
            ->groupBy('account_id')
            ->map(fn ($rows) => $rows->keyBy('side'));

        $accounts = GlAccount::whereIn('type', ['income', 'expense'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $income = $expenses = [];

        foreach ($accounts as $account) {
            $rows = $agg->get($account->id, collect());
            $debits = (float) ($rows->get('debit')->total ?? 0);
            $credits = (float) ($rows->get('credit')->total ?? 0);

            $balance = match ($account->type) {
                'income' => $credits - $debits,
                'expense' => $debits - $credits,
            };

            $row = ['code' => $account->code, 'name' => $account->name, 'amount' => round($balance, 2)];

            match ($account->type) {
                'income' => $income[] = $row,
                'expense' => $expenses[] = $row,
            };
        }

        $totalIncome = round(array_sum(array_column($income, 'amount')), 2);
        $totalExpenses = round(array_sum(array_column($expenses, 'amount')), 2);

        return [
            'from' => $from,
            'to' => $to,
            'income' => $income,
            'expenses' => $expenses,
            'total_income' => $totalIncome,
            'total_expenses' => $totalExpenses,
            'net_income' => round($totalIncome - $totalExpenses, 2),
        ];
    }

    /**
     * Cash Flow — movements in cash/bank accounts for a date range.
     */
    public function cashFlow(?string $from = null, ?string $to = null): array
    {
        $from = $from ? Carbon::parse($from)->toDateString() : now()->startOfMonth()->toDateString();
        $to = $to ? Carbon::parse($to)->toDateString() : now()->toDateString();

        $cashAccounts = GlAccount::whereIn('code', ['1001', '1002'])->pluck('id');

        $rows = DB::table('gl_journal_lines as l')
            ->join('gl_journal_entries as e', 'l.journal_entry_id', '=', 'e.id')
            ->whereIn('l.account_id', $cashAccounts)
            ->whereDate('e.entry_date', '>=', $from)
            ->whereDate('e.entry_date', '<=', $to)
            ->select('e.entry_date as date', 'e.description', 'l.side', 'l.amount')
            ->orderBy('e.entry_date')
            ->get();

        $inflows = 0.0;
        $outflows = 0.0;
        $lines = [];

        foreach ($rows as $row) {
            $amount = (float) $row->amount;
            if ($row->side === 'debit') {
                $inflows += $amount;
            } else {
                $outflows += $amount;
            }
            $lines[] = [
                'date' => $row->date,
                'description' => $row->description,
                'type' => $row->side === 'debit' ? 'inflow' : 'outflow',
                'amount' => $amount,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'inflows' => round($inflows, 2),
            'outflows' => round($outflows, 2),
            'net_flow' => round($inflows - $outflows, 2),
            'lines' => $lines,
        ];
    }

    /**
     * Portfolio at Risk (PAR) by DPD bucket.
     */
    public function portfolioAtRisk(?string $asOf = null): array
    {
        $asOf = $asOf ? Carbon::parse($asOf)->toDateString() : now()->toDateString();

        $totalLoan = (float) Loan::whereIn('status', ['active', 'disbursed'])
            ->sum('outstanding_balance');

        $buckets = [
            'par_1_30' => [1,  30],
            'par_31_60' => [31, 60],
            'par_61_90' => [61, 90],
            'par_90_plus' => [91, 99999],
        ];

        $result = [];
        foreach ($buckets as $key => [$minDpd, $maxDpd]) {
            $minDate = Carbon::parse($asOf)->subDays($maxDpd)->toDateString();
            $maxDate = Carbon::parse($asOf)->subDays($minDpd)->toDateString();

            $amount = (float) Loan::whereIn('status', ['active', 'disbursed'])
                ->whereHas('schedule', function ($q) use ($minDate, $maxDate) {
                    $q->where('is_paid', false)
                        ->where('due_date', '>=', $minDate)
                        ->where('due_date', '<=', $maxDate);
                })
                ->sum('outstanding_balance');

            $result[$key] = [
                'amount' => round($amount, 2),
                'par_pct' => $totalLoan > 0 ? round($amount / $totalLoan * 100, 2) : 0.0,
            ];
        }

        $totalPar = array_sum(array_column($result, 'amount'));

        return [
            'as_of' => $asOf,
            'total_portfolio' => round($totalLoan, 2),
            'total_par' => round($totalPar, 2),
            'total_par_pct' => $totalLoan > 0 ? round($totalPar / $totalLoan * 100, 2) : 0.0,
            'buckets' => $result,
        ];
    }
}
