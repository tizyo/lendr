<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrower_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->string('type');          // e.g. loan_approved, payment_received
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable(); // extra context (loan_id, amount, etc.)
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['borrower_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrower_notifications');
    }
};
