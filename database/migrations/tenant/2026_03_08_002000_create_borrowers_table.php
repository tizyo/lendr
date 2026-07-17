<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            $table->string('borrower_number')->unique(); // LENDR-generated ref
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('other_names')->nullable();
            $table->string('email')->nullable()->unique();
            $table->string('phone')->unique();
            $table->string('phone_alt')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable(); // NRC number
            $table->string('national_id_encrypted')->nullable(); // encrypted version
            $table->string('occupation')->nullable();
            $table->string('employer')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->default('ZM');
            $table->string('next_of_kin_name')->nullable();
            $table->string('next_of_kin_phone')->nullable();
            $table->string('next_of_kin_relationship')->nullable();
            $table->string('avatar')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_blacklisted')->default(false);
            $table->string('blacklist_reason')->nullable();
            // PWA authentication
            $table->string('pin')->nullable(); // bcrypt hashed PIN
            $table->string('otp')->nullable(); // bcrypt hashed OTP
            $table->timestamp('otp_expires_at')->nullable();
            $table->integer('otp_attempts')->default(0);
            $table->boolean('kyc_verified')->default(false);
            $table->string('migration_source')->nullable();
            $table->string('legacy_id')->nullable(); // legacy system ID
            $table->timestamps();
            $table->softDeletes();

            $table->index(['phone', 'is_active']);
            $table->index('borrower_number');
            $table->index('is_blacklisted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};
