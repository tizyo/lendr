<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Central audit trail of every landlord "sign in as" support session. */
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('landlord_user_id');
            $table->string('landlord_email');
            $table->string('tenant_id')->index();
            $table->unsignedBigInteger('target_user_id');
            $table->string('target_name');
            $table->string('target_email');
            $table->string('reason', 500);
            $table->string('token_hash', 64)->nullable()->unique();
            $table->timestamp('link_expires_at');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 20)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};
