<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-level onboarding checklist.
 * One row per step per tenant (created on first access).
 */
return new class extends Migration
{
    public const STEPS = [
        'configure_settings',
        'create_loan_type',
        'invite_staff',
        'create_branch',
        'add_borrower',
        'disburse_first_loan',
    ];

    public function up(): void
    {
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->string('title');
            $table->string('description')->nullable();
            $table->boolean('is_required')->default(true);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('completed_by')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
    }
};
