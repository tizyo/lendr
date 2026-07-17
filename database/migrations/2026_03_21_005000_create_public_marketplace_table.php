<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cross-tenant public loan product marketplace.
 * Stored in central DB so borrowers can browse across opted-in tenants.
 * No sensitive data — only product specs and public tenant branding.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_loan_products', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');                         // which MFI offers this
            $table->string('tenant_name');                       // denormalised display name
            $table->string('tenant_city')->nullable();
            $table->string('product_name');
            $table->string('product_code')->nullable();
            $table->text('description')->nullable();
            $table->decimal('min_amount', 18, 2);
            $table->decimal('max_amount', 18, 2);
            $table->decimal('interest_rate', 8, 4);             // p.a. or per period
            $table->string('interest_type');                     // flat / reducing
            $table->string('interest_period');                   // monthly / yearly
            $table->integer('min_tenure');
            $table->integer('max_tenure');
            $table->string('tenure_type');                       // months / weeks / days
            $table->string('repayment_schedule');
            $table->decimal('processing_fee', 8, 4)->default(0);
            $table->boolean('requires_collateral')->default(false);
            $table->boolean('requires_guarantor')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('applications_count')->default(0);  // soft metric
            $table->timestamps();

            $table->index(['tenant_id', 'is_active']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_loan_products');
    }
};
