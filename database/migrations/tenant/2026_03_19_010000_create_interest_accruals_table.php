<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_interest_accruals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->date('accrual_date');
            $table->decimal('principal_outstanding', 15, 2);
            $table->decimal('daily_rate', 10, 6);          // annual_rate / 365
            $table->decimal('accrued_amount', 15, 2);
            $table->enum('status', ['pending', 'posted', 'reversed'])->default('posted');
            $table->boolean('is_suspended')->default(false); // Stage 3 suspension
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['loan_id', 'accrual_date']);    // no duplicate per day
            $table->index(['accrual_date', 'status']);
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_interest_accruals');
    }
};
