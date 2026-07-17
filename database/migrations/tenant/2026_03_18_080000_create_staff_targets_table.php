<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('period_month'); // 1–12
            $table->unsignedSmallInteger('period_year');
            $table->decimal('disbursement_target', 15, 2)->default(0);
            $table->decimal('collection_target', 15, 2)->default(0);
            $table->unsignedInteger('new_borrowers_target')->default(0);
            $table->unsignedInteger('new_loans_target')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'period_month', 'period_year']);
            $table->index('user_id');
            $table->index(['period_year', 'period_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_targets');
    }
};
