<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loan_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('instalment_number'); // 1, 2, 3...
            $table->date('due_date');
            $table->decimal('principal_due', 15, 2);
            $table->decimal('interest_due', 15, 2);
            $table->decimal('fee_due', 15, 2)->default(0);
            $table->decimal('total_due', 15, 2); // principal + interest + fee
            $table->decimal('principal_paid', 15, 2)->default(0);
            $table->decimal('interest_paid', 15, 2)->default(0);
            $table->decimal('fee_paid', 15, 2)->default(0);
            $table->decimal('penalty_paid', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('outstanding', 15, 2); // = total_due - total_paid
            $table->boolean('is_paid')->default(false);
            $table->date('paid_date')->nullable();
            $table->integer('days_overdue')->default(0);
            $table->decimal('penalty_accrued', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['loan_id', 'instalment_number']);
            $table->index(['loan_id', 'due_date']);
            $table->index(['is_paid', 'due_date']);
        });

        Schema::create('loan_status_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status');
            $table->string('to_status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_status_logs');
        Schema::dropIfExists('loan_schedules');
    }
};
