<?php

use App\Models\Tenant\Expense;
use App\Services\GlLedgerService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approving an expense took the money out of the fund but never posted it to the
 * ledger, so the trial balance and P&L showed no expenses. Post every approved
 * expense that has no journal entry yet (DR 5002 Operating Expenses | CR 1001),
 * on its own expense date. Only the ledger side is added: the fund was already
 * debited at approval. Safe to run again: an expense with an entry is skipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('expenses') || ! Schema::hasTable('gl_journal_entries')) {
            return;
        }

        $ledger = app(GlLedgerService::class);

        Expense::query()
            ->whereIn('status', ['approved', 'paid'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('gl_journal_entries')
                ->where('source_type', Expense::class)
                ->whereColumn('source_id', 'expenses.id'))
            ->orderBy('id')
            ->each(fn (Expense $expense) => $ledger->postExpense($expense, $expense->approved_by));
    }

    public function down(): void
    {
        // Ledger entries are kept: removing them would hide real spending again.
    }
};
