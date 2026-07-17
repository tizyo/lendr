<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ghost Users — cross-tenant public identities.
 *
 * A ghost user is a person who signs up on the public marketplace
 * (without belonging to any tenant). When a tenant later onboards them
 * as a borrower, the borrower record links back here for KYC pull.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ghost_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone')->unique();
            $table->string('email')->nullable()->unique();
            // Identity fields (stored for KYC pull — only the searching hash is indexed)
            $table->string('national_id')->nullable();
            $table->string('tpin_number')->nullable();
            $table->string('company_reg_number')->nullable();
            $table->string('national_id_hash', 64)->nullable()->index();
            $table->string('tpin_hash', 64)->nullable()->index();
            $table->string('company_reg_hash', 64)->nullable()->index();
            // Basic profile
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            // Auth
            $table->boolean('is_phone_verified')->default(false);
            $table->string('otp_code')->nullable();
            $table->timestamp('otp_expires_at')->nullable();
            $table->tinyInteger('otp_attempts')->default(0);
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ghost_users');
    }
};
