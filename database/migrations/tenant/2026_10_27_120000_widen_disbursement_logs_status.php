<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status was an enum (initiated|processing|completed|failed). Payouts also
     * need "unconfirmed" (provider timed out — money may have moved) and
     * "reversed" (rejected and the disbursement undone), so make it a string.
     */
    public function up(): void
    {
        Schema::table('disbursement_logs', function (Blueprint $table) {
            $table->string('status', 20)->default('initiated')->change();

            if (! Schema::hasIndex('disbursement_logs', 'disbursement_logs_loan_id_status_index')) {
                $table->index(['loan_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_logs', function (Blueprint $table) {
            $table->dropIndex(['loan_id', 'status']);
            $table->enum('status', ['initiated', 'processing', 'completed', 'failed'])->default('initiated')->change();
        });
    }
};
