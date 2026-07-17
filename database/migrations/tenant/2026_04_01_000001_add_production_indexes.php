<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Production performance indexes.
 *
 * These composite indexes cover the most frequent query patterns
 * identified in the k6 load tests and dashboard KPI queries.
 */
return new class extends Migration
{
    public function up(): void
    {
        // loans: filter by status + borrower, or status + officer (created_by)
        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasIndex('loans', 'loans_status_borrower_id_index')) {
                $table->index(['status', 'borrower_id'], 'loans_status_borrower_id_index');
            }
            if (! Schema::hasIndex('loans', 'loans_status_created_by_index')) {
                $table->index(['status', 'created_by'], 'loans_status_created_by_index');
            }
            if (! Schema::hasIndex('loans', 'loans_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'loans_status_created_at_index');
            }
        });

        // payments: filter by loan_id + date (most common payment query)
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasIndex('payments', 'payments_loan_id_payment_date_index')) {
                $table->index(['loan_id', 'payment_date'], 'payments_loan_id_payment_date_index');
            }
        });

        // loan_schedules: filter by loan_id + due_date + paid state
        Schema::table('loan_schedules', function (Blueprint $table) {
            if (! Schema::hasIndex('loan_schedules', 'schedules_loan_due_paid_index')) {
                $table->index(['loan_id', 'due_date', 'is_paid'], 'schedules_loan_due_paid_index');
            }
        });

        // borrowers: filter/search by phone and status
        Schema::table('borrowers', function (Blueprint $table) {
            if (! Schema::hasIndex('borrowers', 'borrowers_phone_status_index')) {
                $table->index(['phone', 'is_active'], 'borrowers_phone_status_index');
            }
        });

        // expenses: filter by date range (common in reports)
        Schema::table('expenses', function (Blueprint $table) {
            if (! Schema::hasIndex('expenses', 'expenses_expense_date_status_index')) {
                $table->index(['expense_date', 'status'], 'expenses_expense_date_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndexIfExists('loans_status_borrower_id_index');
            $table->dropIndexIfExists('loans_status_created_by_index');
            $table->dropIndexIfExists('loans_status_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndexIfExists('payments_loan_id_payment_date_index');
        });

        Schema::table('loan_schedules', function (Blueprint $table) {
            $table->dropIndexIfExists('schedules_loan_due_paid_index');
        });

        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropIndexIfExists('borrowers_phone_status_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndexIfExists('expenses_expense_date_status_index');
        });
    }
};
