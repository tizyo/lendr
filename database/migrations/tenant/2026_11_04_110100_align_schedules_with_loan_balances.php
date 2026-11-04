<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Processing and insurance fees are part of what a borrower owes (total_payable),
 * but schedules were built without them (fee_due always 0). The schedule could be
 * fully paid while the loan still showed a balance, and that balance was then
 * booked as principal. For open loans, move the gap between the loan's balance and
 * its schedule into fee_due on the unpaid instalments (the last one if all are
 * paid), so the schedule and the loan agree from now on.
 */
return new class extends Migration
{
    private const OPEN = ['active', 'disbursed', 'defaulted', 'frozen'];

    public function up(): void
    {
        if (! Schema::hasTable('loans') || ! Schema::hasTable('loan_schedules')) {
            return;
        }

        DB::table('loans')->whereIn('status', self::OPEN)->whereNull('deleted_at')->orderBy('id')
            ->each(function ($loan) {
                $rows = DB::table('loan_schedules')->where('loan_id', $loan->id)->orderBy('due_date')->orderBy('instalment_number')->get();
                if ($rows->isEmpty()) {
                    return;
                }

                $scheduled = (string) $rows->sum(fn ($r) => (float) $r->outstanding);
                $gap = bcsub((string) $loan->outstanding_balance, $scheduled, 2);

                if (bccomp($gap, '0.01', 2) < 0) {
                    return;
                }

                $targets = $rows->where('is_paid', false)->values();
                if ($targets->isEmpty()) {
                    $targets = collect([$rows->last()]);
                }

                $share = bcdiv($gap, (string) $targets->count(), 2);
                $remainder = bcsub($gap, bcmul($share, (string) $targets->count(), 2), 2);

                foreach ($targets as $i => $row) {
                    $add = $i === $targets->count() - 1 ? bcadd($share, $remainder, 2) : $share;

                    DB::table('loan_schedules')->where('id', $row->id)->update([
                        'fee_due' => bcadd((string) $row->fee_due, $add, 2),
                        'total_due' => bcadd((string) $row->total_due, $add, 2),
                        'outstanding' => bcadd((string) $row->outstanding, $add, 2),
                        'is_paid' => false,
                        'paid_date' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Data alignment; nothing to undo.
    }
};
