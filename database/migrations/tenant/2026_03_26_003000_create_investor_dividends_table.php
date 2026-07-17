<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investor_dividends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->foreignId('allocation_id')->nullable()->constrained('investor_allocations')->nullOnDelete();
            $table->string('period');                              // e.g. "2026-03"
            $table->decimal('principal', 15, 2)->default(0);
            $table->decimal('return_rate', 8, 4)->default(0);     // annualised %
            $table->decimal('gross_dividend', 15, 2)->default(0);
            $table->decimal('tax_withheld', 15, 2)->default(0);
            $table->decimal('net_dividend', 15, 2)->default(0);
            $table->string('status')->default('pending');          // pending | paid | cancelled
            $table->date('paid_date')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_dividends');
    }
};
