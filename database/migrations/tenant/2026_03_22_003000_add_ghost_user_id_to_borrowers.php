<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link borrowers to their ghost user identity (central DB).
 * No FK constraint — cross-database reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('borrowers') && ! Schema::hasColumn('borrowers', 'ghost_user_id')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->unsignedBigInteger('ghost_user_id')->nullable()->after('id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('borrowers') && Schema::hasColumn('borrowers', 'ghost_user_id')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->dropColumn('ghost_user_id');
            });
        }
    }
};
