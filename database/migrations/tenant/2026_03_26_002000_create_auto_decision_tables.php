<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_decision_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('product_type')->nullable(); // loan product type filter (null = all)
            $table->decimal('min_credit_score', 5, 2)->default(0);
            $table->decimal('max_dti_pct', 5, 2)->nullable();          // max debt-to-income %
            $table->decimal('min_income', 15, 2)->nullable();
            $table->decimal('max_loan_amount', 15, 2)->nullable();
            $table->integer('min_tenure_months')->nullable();
            $table->integer('max_tenure_months')->nullable();
            $table->string('action')->default('approve');               // approve | decline | manual
            $table->integer('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('auto_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('rule_id')->nullable()->constrained('auto_decision_rules')->nullOnDelete();
            $table->string('action');              // approve | decline | manual
            $table->decimal('credit_score', 5, 2)->nullable();
            $table->decimal('dti_pct', 5, 2)->nullable();
            $table->json('factors')->nullable();   // reasons / contributing factors
            $table->text('notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_decisions');
        Schema::dropIfExists('auto_decision_rules');
    }
};
