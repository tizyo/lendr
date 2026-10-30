<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Browser (Web Push) subscriptions for the borrower PWA. For these rows
     * `token` holds the push service endpoint URL, and the two keys are the
     * subscription's encryption keys. Platform was an fcm|apns enum.
     */
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->string('platform', 16)->default('webpush')->change();

            if (! Schema::hasColumn('device_tokens', 'public_key')) {
                $table->string('public_key', 255)->nullable()->after('token'); // p256dh
            }
            if (! Schema::hasColumn('device_tokens', 'auth_token')) {
                $table->string('auth_token', 255)->nullable()->after('public_key'); // auth secret
            }
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table) {
            $table->dropColumn(['public_key', 'auth_token']);
            $table->enum('platform', ['fcm', 'apns'])->default('fcm')->change();
        });
    }
};
