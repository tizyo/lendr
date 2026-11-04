<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which workspace an open-banking API key belongs to, by the key's SHA-256 hash.
 * Integrations call lendr.cc with just their key (there are no workspace
 * subdomains), so the gateway needs this to find the right database.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_key_index')) {
            return;
        }

        Schema::create('api_key_index', function (Blueprint $table) {
            $table->id();
            $table->string('key_hash', 64)->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('api_client_id');
            $table->timestamps();

            $table->index(['tenant_id', 'api_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_index');
    }
};
