<?php

namespace App\Services;

use App\Models\Tenant\BankStatement;
use App\Models\Tenant\BankTransaction;
use App\Models\Tenant\Payment;
use Illuminate\Support\Collection;

class ReconciliationService
{
    /**
     * Parse a CSV string and import rows into a BankStatement.
     * Expected CSV columns (case-insensitive): date, reference, description, amount, type
     */
    public function importCsv(string $csv, string $filename, int $importedBy, array $meta = []): BankStatement
    {
        $lines = array_filter(explode("\n", trim($csv)));
        $header = array_map(fn ($h) => strtolower(trim($h)), str_getcsv(array_shift($lines)));

        $statement = BankStatement::create([
            'filename' => $filename,
            'bank_name' => $meta['bank_name'] ?? null,
            'statement_from' => $meta['statement_from'] ?? null,
            'statement_to' => $meta['statement_to'] ?? null,
            'status' => 'pending',
            'imported_by' => $importedBy,
        ]);

        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv(trim($line));
            if (count($values) < 2) {
                continue;
            }
            $row = array_combine(array_slice($header, 0, count($values)), $values);

            $rows[] = [
                'bank_statement_id' => $statement->id,
                'transaction_date' => $row['date'] ?? now()->toDateString(),
                'reference' => $row['reference'] ?? null,
                'description' => $row['description'] ?? null,
                'amount' => abs((float) ($row['amount'] ?? 0)),
                'type' => strtolower($row['type'] ?? 'credit') === 'debit' ? 'debit' : 'credit',
                'match_status' => 'unmatched',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if (! empty($rows)) {
            BankTransaction::insert($rows);
        }

        $statement->update(['total_rows' => count($rows)]);

        return $statement;
    }

    /**
     * Run reconciliation for a bank statement.
     * Matching strategy:
     * 1. Exact reference match against payments.reference
     * 2. Fuzzy: same date + same amount against unmatched payments
     */
    public function reconcile(BankStatement $statement): array
    {
        $transactions = $statement->transactions()
            ->where('match_status', 'unmatched')
            ->where('type', 'credit')
            ->get();

        $matched = 0;
        $unmatched = 0;

        foreach ($transactions as $tx) {
            $payment = null;

            // Strategy 1: reference match
            if ($tx->reference) {
                $payment = Payment::where('reference', $tx->reference)->first();
            }

            // Strategy 2: date + amount match
            if (! $payment) {
                $payment = Payment::whereDate('payment_date', $tx->transaction_date)
                    ->where('amount', $tx->amount)
                    ->first();
            }

            if ($payment) {
                $tx->update([
                    'match_status' => 'matched',
                    'matched_payment_id' => $payment->id,
                    'match_notes' => 'Auto-matched',
                ]);
                $matched++;
            } else {
                $unmatched++;
            }
        }

        $statement->update([
            'matched_count' => $statement->transactions()->where('match_status', 'matched')->count(),
            'unmatched_count' => $statement->transactions()->where('match_status', 'unmatched')->count(),
            'status' => 'processed',
        ]);

        return ['matched' => $matched, 'unmatched' => $unmatched];
    }

    /**
     * Return unmatched transactions queue (paginated-friendly collection).
     */
    public function unmatchedQueue(BankStatement $statement): Collection
    {
        return $statement->transactions()
            ->where('match_status', 'unmatched')
            ->orderBy('transaction_date')
            ->get();
    }

    /**
     * Manually match a bank transaction to a payment.
     */
    public function manualMatch(BankTransaction $tx, Payment $payment, string $notes = ''): void
    {
        $tx->update([
            'match_status' => 'matched',
            'matched_payment_id' => $payment->id,
            'match_notes' => $notes ?: 'Manually matched',
        ]);

        $tx->bankStatement->increment('matched_count');
        $tx->bankStatement->decrement('unmatched_count');
    }

    /**
     * Mark a transaction as ignored (not applicable / internal transfer).
     */
    public function ignore(BankTransaction $tx, string $reason = ''): void
    {
        $tx->update([
            'match_status' => 'ignored',
            'match_notes' => $reason ?: 'Ignored by user',
        ]);
    }

    /**
     * Summary report for a statement.
     */
    public function report(BankStatement $statement): array
    {
        $txns = $statement->transactions;
        $total = $txns->where('type', 'credit')->sum('amount');

        return [
            'id' => $statement->id,
            'filename' => $statement->filename,
            'bank_name' => $statement->bank_name,
            'statement_from' => $statement->statement_from?->toDateString(),
            'statement_to' => $statement->statement_to?->toDateString(),
            'total_rows' => $statement->total_rows,
            'matched_count' => $statement->matched_count,
            'unmatched_count' => $statement->unmatched_count,
            'match_rate' => $statement->total_rows > 0
                ? round($statement->matched_count / $statement->total_rows * 100, 2)
                : 0,
            'total_credits' => (float) $total,
            'status' => $statement->status,
        ];
    }
}
