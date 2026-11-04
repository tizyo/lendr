<?php

namespace App\Services;

use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class GlLedgerService
{
    /**
     * Post a balanced double-entry journal entry.
     *
     * @param  array  $lines  [['account_code' => '1100', 'side' => 'debit', 'amount' => 5000, 'notes' => '...'], ...]
     * @param  object|null  $source  The source model (e.g. Loan, Payment)
     * @param  string  $entryDate  Y-m-d date string
     * @param  int|null  $createdBy  User ID
     */
    public function post(
        string $description,
        array $lines,
        ?object $source = null,
        string $entryDate = '',
        ?int $createdBy = null,
    ): GlJournalEntry {
        $entryDate = $entryDate ?: now()->toDateString();

        GlAccount::ensureDefaults();

        return DB::transaction(function () use ($description, $lines, $source, $entryDate, $createdBy) {
            $entry = GlJournalEntry::create([
                'reference' => GlJournalEntry::nextReference(),
                'entry_date' => $entryDate,
                'description' => $description,
                'source_type' => $source ? get_class($source) : null,
                'source_id' => $source?->id,
                'created_by' => $createdBy,
            ]);

            foreach ($lines as $line) {
                $account = GlAccount::where('code', $line['account_code'])->firstOrFail();

                $entry->lines()->create([
                    'account_id' => $account->id,
                    'side' => $line['side'],
                    'amount' => $line['amount'],
                    'notes' => $line['notes'] ?? null,
                ]);
            }

            if (! $entry->isBalanced()) {
                throw new \RuntimeException('Journal entry is not balanced (debits ≠ credits).');
            }

            return $entry;
        });
    }

    /**
     * Post a loan disbursement journal entry.
     * DR Loans Receivable | CR Cash on Hand
     */
    public function postDisbursement(object $loan): GlJournalEntry
    {
        return $this->post(
            "Loan disbursement: {$loan->loan_number}",
            [
                ['account_code' => '1100', 'side' => 'debit',  'amount' => $loan->principal_amount],
                ['account_code' => '1001', 'side' => 'credit', 'amount' => $loan->principal_amount],
            ],
            $loan,
            now()->toDateString(),
            auth()->id(),
        );
    }

    /**
     * Payment received: DR Cash on Hand for the whole amount, CR each part where it
     * belongs — Loans Receivable (principal), Interest Income, Fee Income, Penalty
     * Income, and any overpayment held as a customer credit (Accounts Payable).
     * Penalties used to be credited to Loans Receivable, pushing it below zero.
     */
    public function postPayment(object $payment): GlJournalEntry
    {
        $parts = [
            '1100' => ['amount' => (float) ($payment->principal_allocated ?? 0), 'notes' => 'Principal repaid'],
            '4001' => ['amount' => (float) ($payment->interest_allocated ?? 0), 'notes' => 'Interest received'],
            '4002' => ['amount' => (float) ($payment->fee_allocated ?? 0), 'notes' => 'Fees received'],
            '4003' => ['amount' => (float) ($payment->penalty_allocated ?? 0), 'notes' => 'Penalties received'],
            '2100' => ['amount' => (float) ($payment->unallocated_amount ?? 0), 'notes' => 'Overpayment held for the borrower'],
        ];

        $lines = [['account_code' => '1001', 'side' => 'debit', 'amount' => (float) $payment->amount, 'notes' => $payment->receipt_number ?? null]];
        $credited = '0';

        foreach ($parts as $code => $part) {
            if ($part['amount'] > 0) {
                $lines[] = ['account_code' => (string) $code, 'side' => 'credit', 'amount' => $part['amount'], 'notes' => $part['notes']];
                $credited = bcadd($credited, (string) $part['amount'], 2);
            }
        }

        // Anything not broken down (older rows) is principal, so the entry always balances.
        $rest = bcsub((string) $payment->amount, $credited, 2);
        if (bccomp($rest, '0', 2) > 0) {
            $lines[] = ['account_code' => '1100', 'side' => 'credit', 'amount' => (float) $rest, 'notes' => 'Principal repaid'];
        }

        return $this->post(
            "Payment received: {$payment->receipt_number}",
            $lines,
            $payment,
            $payment->payment_date ? Carbon::parse($payment->payment_date)->toDateString() : now()->toDateString(),
            auth()->id(),
        );
    }

    /**
     * Post the mirror image of every journal entry recorded for a source (a reversed
     * payment), so the ledger shows both the original and its reversal.
     */
    public function reverseEntriesFor(object $source, string $description): void
    {
        $entries = GlJournalEntry::with('lines.account')
            ->where('source_type', get_class($source))
            ->where('source_id', $source->id)
            ->get();

        foreach ($entries as $entry) {
            $this->post(
                $description,
                $entry->lines->map(fn ($line) => [
                    'account_code' => $line->account->code,
                    'side' => $line->side === 'debit' ? 'credit' : 'debit',
                    'amount' => (float) $line->amount,
                    'notes' => 'Reversal of '.$entry->reference,
                ])->all(),
                $source, // kept against the same record, so its history shows both
                now()->toDateString(),
                auth()->id(),
            );
        }
    }

    /**
     * Write-off: DR Write-Off Expense | CR Loans Receivable, for the principal still
     * owed. Only principal is ever booked to 1100 (disbursement debits it, repayments
     * credit their principal share); interest and fees are recognised as income when
     * paid, so there's no receivable for them to write off. Writing off the whole
     * outstanding balance used to push Loans Receivable below zero.
     */
    public function postWriteOff(object $loan): ?GlJournalEntry
    {
        $principalRepaid = (float) DB::table('payments')
            ->where('loan_id', $loan->id)
            ->whereNull('deleted_at')
            ->sum('principal_allocated');

        $principalOutstanding = round(max(0, (float) $loan->principal_amount - $principalRepaid), 2);

        if ($principalOutstanding <= 0) {
            return null;
        }

        return $this->post(
            "Loan write-off: {$loan->loan_number}",
            [
                ['account_code' => '5003', 'side' => 'debit', 'amount' => $principalOutstanding, 'notes' => 'Write-off expense (principal outstanding)'],
                ['account_code' => '1100', 'side' => 'credit', 'amount' => $principalOutstanding, 'notes' => 'Loans receivable written off'],
            ],
            $loan,
            now()->toDateString(),
            auth()->id(),
        );
    }

    /**
     * Trial balance: all accounts with their running balances.
     */
    public function trialBalance(): array
    {
        return GlAccount::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->map(fn ($account) => [
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'balance' => $account->balance(),
            ])
            ->toArray();
    }
}
