<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * API keys were stored in plain text (client_key) and looked up by it, so anyone
 * reading the database or a backup had every integration's key. Keep only a
 * SHA-256 hash for lookups and a short preview for staff to recognise the key.
 * Existing keys keep working: their hash is taken from the stored value.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('api_clients')) {
            return;
        }

        if (! Schema::hasColumn('api_clients', 'key_hash')) {
            Schema::table('api_clients', function (Blueprint $table) {
                $table->string('key_hash', 64)->nullable()->unique()->after('client_key');
            });
        }

        DB::table('api_clients')->whereNull('key_hash')->orderBy('id')->each(function ($client) {
            if (! $client->client_key || str_ends_with($client->client_key, '…')) {
                return;
            }

            $hash = hash('sha256', $client->client_key);

            DB::table('api_clients')->where('id', $client->id)->update([
                'key_hash' => $hash,
                'client_key' => substr($client->client_key, 0, 12).'…',
            ]);

            // Register the key centrally so the gateway can find this workspace.
            if ($tenantId = tenant('id')) {
                DB::connection(config('tenancy.database.central_connection'))->table('api_key_index')->updateOrInsert(
                    ['key_hash' => $hash],
                    ['tenant_id' => $tenantId, 'api_client_id' => $client->id, 'created_at' => now(), 'updated_at' => now()],
                );
            }
        });
    }

    public function down(): void
    {
        // The plain keys are gone by design.
    }
};
