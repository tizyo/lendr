<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provision_rates', function (Blueprint $table) {
            $table->id();
            $table->string('stage_label', 30);           // e.g. Stage 1, Stage 2, Stage 3
            $table->unsignedTinyInteger('stage');         // 1, 2, or 3
            $table->unsignedSmallInteger('dpd_from');     // days past due from
            $table->unsignedSmallInteger('dpd_to');       // days past due to (9999 = no limit)
            $table->decimal('provision_rate', 8, 4);      // ECL rate as % of outstanding
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('stage');
            $table->index('is_active');
        });

        Schema::create('loan_provisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->unsignedTinyInteger('stage');               // 1, 2, or 3
            $table->string('stage_label', 30);
            $table->unsignedSmallInteger('days_past_due')->default(0);
            $table->decimal('outstanding_balance', 15, 2);
            $table->decimal('provision_rate', 8, 4);            // % applied
            $table->decimal('provision_amount', 15, 2);         // calculated ECL
            $table->date('calculation_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('loan_id');
            $table->index('stage');
            $table->index('calculation_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_provisions');
        Schema::dropIfExists('provision_rates');
    }
};
