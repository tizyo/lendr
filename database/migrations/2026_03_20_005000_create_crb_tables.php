<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Central Credit Reference Bureau (CRB) tables.
 * Stored in the central / landlord database.
 * All identifiers are stored as SHA-256 hashes — raw PII never stored here.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Core identity record ────────────────────────────────────────────
        Schema::create('crb_identities', function (Blueprint $table) {
            $table->id();
            $table->string('identity_hash', 64)->unique();            // SHA-256 hex
            $table->enum('identity_type', ['nrc', 'tpin', 'company_reg']);
            $table->unsignedSmallInteger('credit_score')->default(600); // 300–850
            $table->enum('score_band', ['excellent', 'very_good', 'good', 'fair', 'poor', 'very_poor'])->default('fair');
            // Loan statistics (aggregated, no tenant identifiers)
            $table->unsignedInteger('total_loans_taken')->default(0);
            $table->unsignedInteger('total_loans_completed')->default(0);
            $table->unsignedInteger('total_loans_defaulted')->default(0);
            $table->unsignedInteger('total_loans_written_off')->default(0);
            $table->unsignedInteger('active_loan_count')->default(0);
            $table->decimal('total_amount_borrowed', 18, 2)->default(0);
            $table->decimal('total_amount_repaid', 18, 2)->default(0);
            $table->date('first_loan_date')->nullable();              // for credit age
            $table->timestamp('last_score_updated_at')->nullable();
            $table->timestamps();

            $table->index('credit_score');
            $table->index('identity_type');
        });

        // ── Score event log ─────────────────────────────────────────────────
        Schema::create('crb_score_events', function (Blueprint $table) {
            $table->id();
            $table->string('identity_hash', 64)->index();
            $table->enum('event_type', [
                'loan_opened',
                'early_repayment',
                'on_time_repayment',
                'late_payment_mild',      // 1–30 DPD
                'late_payment_moderate',  // 31–60 DPD
                'late_payment_severe',    // 61–90 DPD
                'default',               // 90+ DPD
                'loan_completed',
                'writeoff',
                'rehabilitation',         // written-off loan later repaid
                'multiple_loans_penalty',
                'inquiry',               // hard inquiry at loan application
            ]);
            $table->smallInteger('points_change');     // signed
            $table->unsignedSmallInteger('score_before');
            $table->unsignedSmallInteger('score_after');
            $table->unsignedSmallInteger('dpd')->nullable();          // days past due at event
            $table->string('tenant_id')->nullable();                  // for audit (not exposed via API)
            $table->string('loan_reference_hash', 64)->nullable();    // SHA-256 of loan number
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['identity_hash', 'event_type']);
            $table->index(['identity_hash', 'created_at']);
        });

        // ── Inquiry log (hard & soft checks) ────────────────────────────────
        Schema::create('crb_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('identity_hash', 64)->index();
            $table->string('tenant_id');                              // which tenant checked
            $table->enum('purpose', ['loan_disbursement', 'manual_check', 'kyc_check']);
            $table->unsignedSmallInteger('result_score')->nullable();
            $table->unsignedTinyInteger('result_active_loans')->default(0);
            $table->string('result_risk_level')->nullable();          // excellent/good/fair/poor/very_poor
            $table->boolean('result_has_active_loans')->default(false);
            $table->boolean('override_requested')->default(false);
            $table->text('override_reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['identity_hash', 'tenant_id']);
            $table->index(['identity_hash', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crb_inquiries');
        Schema::dropIfExists('crb_score_events');
        Schema::dropIfExists('crb_identities');
    }
};
