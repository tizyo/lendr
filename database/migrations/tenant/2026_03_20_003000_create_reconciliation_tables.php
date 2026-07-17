<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('bank_name')->nullable();
            $table->date('statement_from')->nullable();
            $table->date('statement_to')->nullable();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('bank_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_statement_id')->constrained('bank_statements')->cascadeOnDelete();
            $table->date('transaction_date');
            $table->string('reference')->nullable();
            $table->string('description')->nullable();
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['credit', 'debit'])->default('credit');
            $table->enum('match_status', ['matched', 'unmatched', 'ignored'])->default('unmatched');
            $table->foreignId('matched_payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->text('match_notes')->nullable();
            $table->timestamps();

            $table->index(['bank_statement_id', 'match_status']);
            $table->index('reference');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transactions');
        Schema::dropIfExists('bank_statements');
    }
};
