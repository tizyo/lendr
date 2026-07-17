<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central DB — cross-tenant credit score ledger.
 *
 * borrower_global_id = SHA256(phone_number) for privacy-preserving
 * cross-tenant identity. Raw phone is never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credit_scores', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_global_id', 64)->unique()
                  ->comment('SHA256 of phone number — cross-tenant identity hash');

            // Composite score
            $table->smallInteger('score')->default(300);
            $table->enum('score_band', ['poor', 'fair', 'good', 'excellent'])->default('poor');

            // Factor subscores (0–100 each)
            $table->unsignedTinyInteger('repayment_history_score')->default(0)
                  ->comment('Weight 40% — on-time payments ratio');
            $table->unsignedTinyInteger('debt_load_score')->default(0)
                  ->comment('Weight 25% — outstanding vs income');
            $table->unsignedTinyInteger('history_length_score')->default(0)
                  ->comment('Weight 15% — months since first loan');
            $table->unsignedTinyInteger('account_mix_score')->default(0)
                  ->comment('Weight 10% — diversity of loan types');
            $table->unsignedTinyInteger('new_credit_score')->default(0)
                  ->comment('Weight 10% — applications in last 6 months (fewer = better)');

            // Summary stats
            $table->unsignedInteger('total_loans')->default(0);
            $table->unsignedInteger('total_completed')->default(0);
            $table->unsignedInteger('total_defaulted')->default(0);

            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->index('score_band');
            $table->index('score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_scores');
    }
};
