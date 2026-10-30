<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scope 6.4.2 — bank-deposit repayments: the borrower pays at the bank using
 * their loan number as reference, uploads the deposit slip, and staff verify
 * it against the bank before it becomes a recorded payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_proofs')) {
            return;
        }

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->date('paid_on');
            $table->string('bank_reference', 100)->nullable(); // deposit slip / transaction number
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type', 100)->nullable();
            $table->unsignedInteger('file_size')->nullable();
            $table->string('status', 16)->default('pending'); // pending | approved | rejected
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('borrower_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
