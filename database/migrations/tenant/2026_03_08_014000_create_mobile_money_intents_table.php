<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->restrictOnDelete();
            $table->foreignId('borrower_id')->constrained()->restrictOnDelete();
            $table->string('reference')->unique();          // LENDR-generated external reference
            $table->string('provider');                     // airtel_money, mtn_momo, zamtel_kwacha
            $table->string('phone');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('ZMW');
            $table->enum('status', ['pending', 'confirmed', 'failed', 'expired'])->default('pending');
            $table->string('provider_transaction_id')->nullable();
            $table->text('provider_response')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['reference', 'status']);
            $table->index(['borrower_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_intents');
    }
};
