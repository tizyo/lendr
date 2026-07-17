<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Commission rules (per staff / global) ──────────────────────────────
        Schema::create('commission_rules', function (Blueprint $table) {
            $table->id();
            // null = applies to all staff matching the role
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            // null = applies to all loan types
            $table->unsignedBigInteger('loan_type_id')->nullable();
            $table->foreign('loan_type_id')->references('id')->on('loan_types')->nullOnDelete();
            $table->enum('trigger', ['disbursement', 'repayment', 'loan_completion']);
            $table->enum('calc_type', ['percentage', 'flat']);
            $table->decimal('rate', 8, 4)->default(0);       // % or flat amount
            $table->decimal('min_amount', 18, 2)->nullable(); // min loan amount to qualify
            $table->decimal('max_amount', 18, 2)->nullable(); // max loan amount cap
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── Earned commissions ─────────────────────────────────────────────────
        Schema::create('staff_commissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unsignedBigInteger('loan_id')->nullable();
            $table->foreign('loan_id')->references('id')->on('loans')->nullOnDelete();
            $table->unsignedBigInteger('rule_id')->nullable();
            $table->foreign('rule_id')->references('id')->on('commission_rules')->nullOnDelete();
            $table->enum('trigger', ['disbursement', 'repayment', 'loan_completion']);
            $table->decimal('base_amount', 18, 2);  // loan amount or payment amount
            $table->decimal('commission_amount', 18, 2);
            $table->enum('status', ['pending', 'approved', 'paid', 'cancelled'])->default('pending');
            $table->date('period_month');             // YYYY-MM-01 for grouping
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'period_month']);
            $table->index(['status', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_commissions');
        Schema::dropIfExists('commission_rules');
    }
};
