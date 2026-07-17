<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_email_configs', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255)->nullable();
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('encryption', 8)->default('tls'); // tls|ssl|
            $table->string('username', 255)->nullable();
            $table->text('password')->nullable();            // encrypted
            $table->string('from_address', 255)->nullable();
            $table->string('from_name', 128)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_email_configs');
    }
};
