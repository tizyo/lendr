<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Offline field sync: a phone that lost signal after the server had processed a
 * batch sent the same batch again, and every payment in it was recorded twice.
 * Each item now carries a key (the device's id for it, or a fingerprint of its
 * contents); an item already processed under that key is answered, not redone.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('offline_sync_queue') || Schema::hasColumn('offline_sync_queue', 'client_key')) {
            return;
        }

        Schema::table('offline_sync_queue', function (Blueprint $table) {
            $table->string('client_key', 64)->nullable()->after('action');
            $table->unique(['user_id', 'client_key']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('offline_sync_queue', 'client_key')) {
            return;
        }

        Schema::table('offline_sync_queue', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'client_key']);
            $table->dropColumn('client_key');
        });
    }
};
