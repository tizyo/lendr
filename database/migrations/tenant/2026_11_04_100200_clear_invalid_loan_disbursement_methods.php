<?php

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bulk Loans used to save the disbursement method as typed ("mobile_money",
 * "cheque"), which isn't a PaymentMethod value, so loading such a loan threw and
 * its page failed. The real channel isn't recoverable: clear the value and note
 * it on the loan's history.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('loans') || ! Schema::hasColumn('loans', 'disbursement_method')) {
            return;
        }

        $valid = array_column(PaymentMethod::cases(), 'value');

        $bad = DB::table('loans')
            ->whereNotNull('disbursement_method')
            ->whereNotIn('disbursement_method', $valid)
            ->get(['id', 'status', 'disbursement_method']);

        foreach ($bad as $loan) {
            DB::table('loans')->where('id', $loan->id)->update(['disbursement_method' => null]);

            if (Schema::hasTable('loan_status_logs')) {
                DB::table('loan_status_logs')->insert([
                    'loan_id' => $loan->id,
                    'changed_by' => null,
                    'from_status' => $loan->status,
                    'to_status' => $loan->status,
                    'notes' => "Disbursement method \"{$loan->disbursement_method}\" was not a valid channel and has been cleared.",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Invalid values can't be restored meaningfully.
    }
};
