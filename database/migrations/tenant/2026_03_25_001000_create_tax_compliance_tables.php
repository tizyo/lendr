<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-tenant tax configuration
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('tax_type', 30);           // 'wht', 'vat', 'excise'
            $table->decimal('rate', 8, 4);             // e.g. 15.0000 for 15%
            $table->string('label', 100)->nullable();  // display name
            $table->boolean('applies_to_interest')->default(true);
            $table->boolean('applies_to_fees')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Computed tax per payment (WHT deducted at source, VAT on fees, etc.)
        Schema::create('tax_computations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 30);         // 'payment', 'fee', 'penalty'
            $table->unsignedBigInteger('source_id');
            $table->decimal('taxable_amount', 14, 2);
            $table->decimal('tax_amount', 14, 2);
            $table->string('period', 7);               // 'YYYY-MM'
            $table->string('status', 20)->default('computed');  // computed|remitted
            $table->timestamp('remitted_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_computations');
        Schema::dropIfExists('tax_configurations');
    }
};
