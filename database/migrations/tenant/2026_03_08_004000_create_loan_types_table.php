<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('requires_guarantor')->default(false);
            $table->json('required_documents')->nullable(); // array of document_type keys
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('loan_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_type_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            // Interest
            $table->decimal('interest_rate', 8, 4); // e.g. 5.5000 = 5.5%
            $table->enum('interest_type', ['flat', 'reducing_balance', 'compound'])->default('flat');
            $table->enum('interest_period', ['daily', 'weekly', 'monthly', 'annually'])->default('monthly');
            // Tenure
            $table->integer('min_tenure');
            $table->integer('max_tenure');
            $table->enum('tenure_type', ['days', 'weeks', 'months'])->default('months');
            // Amounts
            $table->decimal('min_amount', 15, 2);
            $table->decimal('max_amount', 15, 2);
            // Penalties
            $table->decimal('penalty_rate', 8, 4)->default(0); // % per day overdue
            $table->enum('penalty_type', ['flat', 'percentage'])->default('percentage');
            $table->integer('grace_period_days')->default(0);
            // Repayment
            $table->enum('repayment_schedule', ['daily', 'weekly', 'bi_weekly', 'monthly', 'bullet'])->default('monthly');
            // Fees
            $table->decimal('processing_fee', 8, 4)->default(0); // % of loan amount
            $table->decimal('insurance_fee', 8, 4)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['loan_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_plans');
        Schema::dropIfExists('loan_types');
    }
};
