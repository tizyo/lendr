<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investors', function (Blueprint $table) {
            $table->id();
            $table->string('investor_number', 30)->unique();
            $table->string('name');
            $table->string('email', 150)->unique();
            $table->string('phone', 30)->nullable();
            $table->enum('type', ['individual', 'institution'])->default('individual');
            $table->string('national_id', 60)->nullable();
            $table->string('address')->nullable();
            $table->string('country', 60)->default('ZM');
            $table->enum('status', ['active', 'suspended', 'exited'])->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
        });

        Schema::create('investor_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('investor_id')->constrained('investors')->cascadeOnDelete();
            $table->foreignId('loan_id')->constrained('loans')->cascadeOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->decimal('allocated_amount', 15, 2);    // amount invested in this loan
            $table->decimal('expected_return', 15, 2)->default(0);  // projected interest earned
            $table->decimal('actual_return', 15, 2)->default(0);    // realised interest so far
            $table->enum('status', ['active', 'settled', 'defaulted'])->default('active');
            $table->date('allocation_date');
            $table->date('settled_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('investor_id');
            $table->index('loan_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investor_allocations');
        Schema::dropIfExists('investors');
    }
};
