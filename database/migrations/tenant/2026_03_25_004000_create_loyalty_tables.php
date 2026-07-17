<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Points ledger — one row per earn/redeem event
        Schema::create('loyalty_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->constrained('borrowers')->cascadeOnDelete();
            $table->integer('points');                      // positive = earn, negative = redeem
            $table->string('type');                         // earned | redeemed | expired | adjusted
            $table->string('description')->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();
        });

        // Tier config — admin-defined thresholds
        Schema::create('loyalty_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                         // Bronze | Silver | Gold | Platinum
            $table->integer('min_points')->default(0);
            $table->decimal('fee_discount_pct', 5, 2)->default(0); // % discount on processing fee
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Current loyalty state per borrower
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('borrower_id')->unique()->constrained('borrowers')->cascadeOnDelete();
            $table->integer('total_points')->default(0);
            $table->string('tier')->default('Bronze');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_accounts');
        Schema::dropIfExists('loyalty_tiers');
        Schema::dropIfExists('loyalty_points');
    }
};
