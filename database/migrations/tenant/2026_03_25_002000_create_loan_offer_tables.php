<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rules that trigger automatic loan offer generation
        Schema::create('loan_offer_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedSmallInteger('min_credit_score')->default(600);
            $table->unsignedSmallInteger('max_credit_score')->default(850);
            $table->foreignId('loan_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('min_offered_amount', 14, 2);
            $table->decimal('max_offered_amount', 14, 2);
            $table->unsignedSmallInteger('validity_days')->default(30);  // offer expires after N days
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Generated loan offers per borrower
        Schema::create('loan_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_offer_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('borrower_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loan_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('offered_amount', 14, 2);
            $table->decimal('interest_rate', 8, 4);
            $table->unsignedSmallInteger('tenure');
            $table->unsignedSmallInteger('credit_score_at_offer')->nullable();
            $table->string('status', 20)->default('pending');  // pending|accepted|declined|expired
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->foreignId('created_loan_id')->nullable()->constrained('loans')->nullOnDelete();
            $table->timestamps();

            $table->index(['borrower_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_offers');
        Schema::dropIfExists('loan_offer_rules');
    }
};
