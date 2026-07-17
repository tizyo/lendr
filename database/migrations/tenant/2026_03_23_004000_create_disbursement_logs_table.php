<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant log of every auto-disbursement attempt (Phase 54).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disbursement_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->string('gateway');                        // flutterwave|mtn_momo|airtel_money|pawapay|zamtel_kwacha
            $table->string('reference')->nullable();          // LENDR-DISB-{loan_id}-{ts}
            $table->string('provider_reference')->nullable(); // provider's own transaction/payout ID
            $table->decimal('amount', 15, 2);
            $table->string('recipient_phone')->nullable();
            $table->enum('status', ['initiated', 'processing', 'completed', 'failed'])->default('initiated');
            $table->json('provider_response')->nullable();
            $table->text('failure_reason')->nullable();
            $table->boolean('used_wallet')->default(false);   // true = Enterprise wallet; false = tenant settings
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disbursement_logs');
    }
};
