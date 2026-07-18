<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collateral_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('loan_id');
            $table->enum('type', ['property', 'vehicle', 'equipment', 'land', 'savings', 'other'])->default('other');
            $table->string('description');
            $table->decimal('estimated_value', 15, 2)->nullable();
            $table->decimal('assessed_value', 15, 2)->nullable();
            $table->date('assessment_date')->nullable();
            $table->text('location')->nullable();
            $table->enum('status', ['pending', 'verified', 'released'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('loan_id')->references('id')->on('loans')->cascadeOnDelete();
            $table->index('loan_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collateral_items');
    }
};
