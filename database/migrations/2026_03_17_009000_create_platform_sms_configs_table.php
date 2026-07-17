<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_sms_configs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 32)->unique();   // africas_talking|sms_to
            $table->boolean('is_active')->default(false);
            $table->text('api_key')->nullable();        // encrypted
            $table->string('username', 128)->nullable(); // AfricasTalking username
            $table->string('sender_id', 32)->nullable(); // sender name / ID
            $table->boolean('sandbox')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_sms_configs');
    }
};
