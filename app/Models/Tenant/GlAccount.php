<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlAccount extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'is_active',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function journalLines(): HasMany
    {
        return $this->hasMany(GlJournalLine::class, 'account_id');
    }

    // ─── Balance ──────────────────────────────────────────────────────────────

    /**
     * Running balance: debits increase assets/expenses; credits increase liabilities/equity/income.
     */
    public function balance(): float
    {
        $debits = (float) $this->journalLines()->where('side', 'debit')->sum('amount');
        $credits = (float) $this->journalLines()->where('side', 'credit')->sum('amount');

        return match ($this->type) {
            'asset', 'expense' => $debits - $credits,
            'liability', 'equity', 'income' => $credits - $debits,
        };
    }

    // ─── Default Chart of Accounts ────────────────────────────────────────────

    public static function defaultAccounts(): array
    {
        return [
            // Assets
            ['code' => '1001', 'name' => 'Cash on Hand',          'type' => 'asset'],
            ['code' => '1002', 'name' => 'Bank Account',           'type' => 'asset'],
            ['code' => '1100', 'name' => 'Loans Receivable',       'type' => 'asset'],
            ['code' => '1200', 'name' => 'Interest Receivable',    'type' => 'asset'],
            ['code' => '1300', 'name' => 'Fee Receivable',         'type' => 'asset'],
            // Liabilities
            ['code' => '2001', 'name' => 'Savings Deposits',       'type' => 'liability'],
            ['code' => '2100', 'name' => 'Accounts Payable',       'type' => 'liability'],
            // Equity
            ['code' => '3001', 'name' => 'Capital Fund',           'type' => 'equity'],
            ['code' => '3100', 'name' => 'Retained Earnings',      'type' => 'equity'],
            // Income
            ['code' => '4001', 'name' => 'Interest Income',        'type' => 'income'],
            ['code' => '4002', 'name' => 'Fee Income',             'type' => 'income'],
            ['code' => '4003', 'name' => 'Penalty Income',         'type' => 'income'],
            // Expenses
            ['code' => '5001', 'name' => 'Provision for Bad Debt', 'type' => 'expense'],
            ['code' => '5002', 'name' => 'Operating Expenses',     'type' => 'expense'],
            ['code' => '5003', 'name' => 'Write-Off Expense',      'type' => 'expense'],
        ];
    }
}
