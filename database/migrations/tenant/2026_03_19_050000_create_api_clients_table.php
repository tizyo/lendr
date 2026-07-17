<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('client_key', 64)->unique();        // public key (X-API-Key)
            $table->string('client_secret', 255);              // hashed secret for signing
            $table->json('scopes')->nullable();                // ['loan_apply','loan_status','payment_initiate','products_read']
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('rate_limit_per_minute')->default(60);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->string('endpoint', 200);
            $table->string('method', 10);
            $table->string('ip_address', 45);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('response_time_ms')->default(0);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['api_client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_access_logs');
        Schema::dropIfExists('api_clients');
    }
};
