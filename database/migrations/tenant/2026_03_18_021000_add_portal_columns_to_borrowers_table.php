<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('otp_attempts');
            $table->boolean('portal_access')->default(true)->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('borrowers', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'portal_access']);
        });
    }
};
