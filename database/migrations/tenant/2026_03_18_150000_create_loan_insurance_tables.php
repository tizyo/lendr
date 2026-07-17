<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insurance_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->unique();
            $table->text('description')->nullable();
            $table->enum('premium_type', ['flat', 'percentage'])->default('percentage');
            $table->decimal('premium_rate', 8, 4)->default(0);   // % of principal or flat fee
            $table->enum('coverage_type', ['credit_life', 'disability', 'property', 'comprehensive'])->default('credit_life');
            $table->unsignedTinyInteger('max_term_months')->nullable();  // max loan term eligible
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });

        Schema::create('loan_insurances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('insurance_product_id')->constrained('insurance_products')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('policy_number', 80)->unique()->nullable();
            $table->decimal('sum_insured', 15, 2);       // coverage amount
            $table->decimal('premium_amount', 15, 2);    // calculated premium
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->enum('status', ['active', 'lapsed', 'cancelled', 'claimed'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('loan_id');
            $table->index('insurance_product_id');
            $table->index('status');
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_insurance_id')->constrained('loan_insurances')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('claim_number', 60)->unique();
            $table->enum('claim_type', ['death', 'disability', 'property_damage', 'other'])->default('other');
            $table->decimal('claim_amount', 15, 2);
            $table->decimal('approved_amount', 15, 2)->nullable();
            $table->enum('status', ['pending', 'under_review', 'approved', 'paid', 'rejected'])->default('pending');
            $table->date('incident_date');
            $table->text('description')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('loan_insurance_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insurance_claims');
        Schema::dropIfExists('loan_insurances');
        Schema::dropIfExists('insurance_products');
    }
};
