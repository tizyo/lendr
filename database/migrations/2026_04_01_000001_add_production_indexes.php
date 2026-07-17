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
        // loans: filter by status + borrower, or status + officer
        Schema::table('loans', function (Blueprint $table) {
            if (! $this->hasIndex('loans', 'loans_status_borrower_id_index')) {
                $table->index(['status', 'borrower_id'], 'loans_status_borrower_id_index');
            }
            if (! $this->hasIndex('loans', 'loans_status_loan_officer_id_index')) {
                $table->index(['status', 'loan_officer_id'], 'loans_status_loan_officer_id_index');
            }
            if (! $this->hasIndex('loans', 'loans_status_created_at_index')) {
                $table->index(['status', 'created_at'], 'loans_status_created_at_index');
            }
        });

        // payments: filter by loan_id + date (most common payment query)
        Schema::table('payments', function (Blueprint $table) {
            if (! $this->hasIndex('payments', 'payments_loan_id_payment_date_index')) {
                $table->index(['loan_id', 'payment_date'], 'payments_loan_id_payment_date_index');
            }
        });

        // repayment_schedules: filter by loan_id + due_date + status
        Schema::table('repayment_schedules', function (Blueprint $table) {
            if (! $this->hasIndex('repayment_schedules', 'schedules_loan_due_status_index')) {
                $table->index(['loan_id', 'due_date', 'status'], 'schedules_loan_due_status_index');
            }
        });

        // borrowers: filter/search by phone and status
        Schema::table('borrowers', function (Blueprint $table) {
            if (! $this->hasIndex('borrowers', 'borrowers_phone_status_index')) {
                $table->index(['phone', 'is_active'], 'borrowers_phone_status_index');
            }
        });

        // expenses: filter by date range (common in reports)
        Schema::table('expenses', function (Blueprint $table) {
            if (! $this->hasIndex('expenses', 'expenses_expense_date_status_index')) {
                $table->index(['expense_date', 'status'], 'expenses_expense_date_status_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loans', function (Blueprint $table) {
            $table->dropIndexIfExists('loans_status_borrower_id_index');
            $table->dropIndexIfExists('loans_status_loan_officer_id_index');
            $table->dropIndexIfExists('loans_status_created_at_index');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndexIfExists('payments_loan_id_payment_date_index');
        });

        Schema::table('repayment_schedules', function (Blueprint $table) {
            $table->dropIndexIfExists('schedules_loan_due_status_index');
        });

        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropIndexIfExists('borrowers_phone_status_index');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndexIfExists('expenses_expense_date_status_index');
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        try {
            $indexes = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
