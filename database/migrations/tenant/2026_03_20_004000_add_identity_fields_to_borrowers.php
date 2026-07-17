<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add TPIN and Company Registration Number to borrowers.
 * Also makes national_id (NRC) unique within the tenant.
 * Note: uniqueness is enforced at the tenant (schema) level only —
 *       the same NRC may exist in another tenant's schema without conflict.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->string('tpin_number')->nullable()->after('national_id');
            $table->string('company_reg_number')->nullable()->after('tpin_number');

            // Unique within this tenant's DB schema
            $table->unique('national_id');
            $table->unique('tpin_number');
            $table->unique('company_reg_number');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropUnique(['national_id']);
            $table->dropUnique(['tpin_number']);
            $table->dropUnique(['company_reg_number']);
            $table->dropColumn(['tpin_number', 'company_reg_number']);
        });
    }
};
