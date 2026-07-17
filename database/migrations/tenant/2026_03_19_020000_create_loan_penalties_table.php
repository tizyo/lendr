<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('schedule_id')->nullable()->constrained('loan_schedules')->nullOnDelete();
            $table->date('penalty_date');
            $table->integer('days_overdue')->default(0);
            $table->decimal('penalty_rate', 8, 4);          // % per day of overdue amount
            $table->decimal('overdue_amount', 15, 2);
            $table->decimal('penalty_amount', 15, 2);
            $table->decimal('waived_amount', 15, 2)->default(0);
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable();
            $table->text('waiver_reason')->nullable();
            $table->enum('status', ['pending', 'applied', 'waived'])->default('applied');
            $table->timestamps();

            $table->index(['loan_id', 'penalty_date']);
            $table->index(['schedule_id', 'penalty_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_penalties');
    }
};
