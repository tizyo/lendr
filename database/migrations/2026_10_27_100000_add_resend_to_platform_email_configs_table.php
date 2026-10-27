<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_email_configs', function (Blueprint $table) {
            // smtp | resend — DigitalOcean blocks outbound SMTP, so Resend (HTTPS API) is the production default.
            $table->string('driver', 16)->default('smtp')->after('id');
            $table->text('api_key')->nullable()->after('password'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('platform_email_configs', function (Blueprint $table) {
            $table->dropColumn(['driver', 'api_key']);
        });
    }
};
