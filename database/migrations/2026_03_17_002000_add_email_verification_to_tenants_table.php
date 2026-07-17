<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('admin_email')->nullable()->after('slug');
            $table->string('email_verification_token', 64)->nullable()->unique()->after('admin_email');
            $table->timestamp('email_verified_at')->nullable()->after('email_verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['admin_email', 'email_verification_token', 'email_verified_at']);
        });
    }
};
