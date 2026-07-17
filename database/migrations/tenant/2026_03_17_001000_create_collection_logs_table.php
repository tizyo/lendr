<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collection_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('officer_id')->nullable()->constrained('users')->nullOnDelete();

            // How the borrower was contacted
            $table->enum('contact_method', ['call', 'sms', 'visit', 'email', 'whatsapp'])->default('call');

            // Outcome of the contact
            $table->enum('outcome', [
                'reached',           // Connected, discussed repayment
                'no_answer',         // No response
                'promised_payment',  // Borrower committed to pay
                'partial_payment',   // Collected partial amount on the spot
                'paid_up',           // Fully cleared during visit
                'refused',           // Borrower refused to engage
                'invalid_number',    // Wrong/disconnected number
                'rescheduled',       // New date agreed
            ])->default('reached');

            $table->text('notes')->nullable();
            $table->date('follow_up_date')->nullable();

            // Optional amounts recorded during the contact
            $table->decimal('amount_promised', 15, 2)->nullable();
            $table->decimal('amount_collected', 15, 2)->nullable();

            $table->timestamps();

            $table->index(['loan_id', 'created_at']);
            $table->index('officer_id');
            $table->index('follow_up_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_logs');
    }
};
