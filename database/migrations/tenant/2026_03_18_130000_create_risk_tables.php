<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_policies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rule_type', 60); // max_active_loans | min_credit_score | max_loan_to_income | blacklisted_region | blacklisted_employer | max_loan_amount | min_borrower_age
            $table->string('operator', 10)->default('gte'); // gte | lte | eq | in | not_in
            $table->text('value');                          // scalar or JSON array
            $table->enum('action', ['block', 'warn'])->default('warn');
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_active');
            $table->index('rule_type');
        });

        Schema::create('risk_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('risk_policy_id')->constrained('risk_policies')->cascadeOnDelete();
            $table->enum('severity', ['warn', 'block']);
            $table->text('detail');                        // human-readable explanation
            $table->boolean('overridden')->default(false);
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('override_reason')->nullable();
            $table->timestamp('overridden_at')->nullable();
            $table->timestamps();

            $table->index('loan_id');
            $table->index('risk_policy_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_flags');
        Schema::dropIfExists('risk_policies');
    }
};
