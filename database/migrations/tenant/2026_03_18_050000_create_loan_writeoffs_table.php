<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_writeoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('written_off_by')->constrained('users')->restrictOnDelete();
            $table->decimal('written_off_amount', 15, 2);
            $table->text('reason');
            $table->decimal('total_recovered', 15, 2)->default(0);
            $table->timestamps();

            $table->index('loan_id');
        });

        Schema::create('loan_writeoff_recoveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_writeoff_id')->constrained('loan_writeoffs')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('method', 50)->default('cash'); // cash, bank_transfer, mobile_money
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->date('recovery_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_writeoff_recoveries');
        Schema::dropIfExists('loan_writeoffs');
    }
};
