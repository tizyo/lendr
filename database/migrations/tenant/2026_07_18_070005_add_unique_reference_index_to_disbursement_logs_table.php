<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The disbursement reference is now deterministic per loan (see
 * AutoDisbursementService) rather than time()-suffixed, so a unique index
 * is what actually prevents a retried/duplicated disbursement attempt from
 * paying a borrower twice — the application-level check alone can't close
 * the race between two concurrent first attempts on the same loan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disbursement_logs', function (Blueprint $table) {
            $table->unique('reference');
        });
    }

    public function down(): void
    {
        Schema::table('disbursement_logs', function (Blueprint $table) {
            $table->dropUnique(['reference']);
        });
    }
};
