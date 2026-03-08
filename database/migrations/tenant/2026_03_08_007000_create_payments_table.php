<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number')->unique(); // LENDR-generated receipt
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 15, 2);
            $table->decimal('principal_allocated', 15, 2)->default(0);
            $table->decimal('interest_allocated', 15, 2)->default(0);
            $table->decimal('penalty_allocated', 15, 2)->default(0);
            $table->decimal('fee_allocated', 15, 2)->default(0);
            $table->string('payment_method'); // PaymentMethod enum value
            $table->date('payment_date');
            $table->string('reference')->nullable(); // bank ref, momo ref
            $table->string('momo_transaction_id')->nullable();
            $table->string('momo_provider')->nullable();
            $table->enum('source', ['manual', 'mobile_money_webhook', 'migration'])->default('manual');
            $table->boolean('is_overdue_payment')->default(false);
            $table->text('notes')->nullable();
            $table->string('legacy_payment_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['loan_id', 'payment_date']);
            $table->index('payment_date');
            $table->index('receipt_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
