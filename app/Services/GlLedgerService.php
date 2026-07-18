<?php

namespace App\Services;

use App\Models\Tenant\GlAccount;
use App\Models\Tenant\GlJournalEntry;
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
     * Post a payment received journal entry.
     * DR Cash on Hand | CR Loans Receivable (principal) + Interest Income (interest) + Fee Income (fees)
     */
    public function postPayment(object $payment): GlJournalEntry
    {
        $lines = [
            ['account_code' => '1001', 'side' => 'debit', 'amount' => $payment->amount],
        ];

        $principal = (float) ($payment->principal_allocated ?? $payment->amount);
        $interest = (float) ($payment->interest_allocated ?? 0);
        $fees = (float) ($payment->fee_allocated ?? 0);

        if ($principal > 0) {
            $lines[] = ['account_code' => '1100', 'side' => 'credit', 'amount' => $principal];
        }
        if ($interest > 0) {
            $lines[] = ['account_code' => '4001', 'side' => 'credit', 'amount' => $interest];
        }
        if ($fees > 0) {
            $lines[] = ['account_code' => '4002', 'side' => 'credit', 'amount' => $fees];
        }

        // Adjust principal if interest/fees don't account for the full amount
        $credited = $principal + $interest + $fees;
        if (abs($credited - $payment->amount) > 0.01) {
            // Correct the principal line amount
            foreach ($lines as &$l) {
                if ($l['account_code'] === '1100') {
                    $l['amount'] = round($payment->amount - $interest - $fees, 2);
                    break;
                }
            }
        }

        return $this->post(
            "Payment received for loan #{$payment->loan_id}",
            $lines,
            $payment,
            $payment->payment_date ?? now()->toDateString(),
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
