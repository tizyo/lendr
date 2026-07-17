<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant standing orders for auto-debit repayments (Phase 55).
 * One row per loan schedule instalment for Enterprise tenants with debit_enabled wallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standing_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('loan_schedule_id')->nullable()->constrained('loan_schedules')->nullOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers');
            $table->decimal('amount', 15, 2);
            $table->string('phone');                          // phone number to debit
            $table->string('gateway');                        // from TenantWallet.gateway
            $table->date('due_date');                         // instalment due date
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->unsignedTinyInteger('retry_count')->default(0);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->timestamp('next_attempt_at')->nullable(); // null = due_date, set on retry
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('provider_reference')->nullable(); // LENDR-DEBIT-{id}
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_attempt_at']);
            $table->index(['loan_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standing_orders');
    }
};
