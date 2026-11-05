<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company names are stored in capitals (Tenant::name mutator) and owner emails in
 * lower case; bring the workspaces created before that into line. Workspace IDs
 * (slugs) are left exactly as they are: people sign in with them.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        DB::table('tenants')->update([
            'name' => DB::raw('UPPER(TRIM(name))'),
            'admin_email' => DB::raw('LOWER(TRIM(admin_email))'),
        ]);
    }

    public function down(): void
    {
        // The original capitalisation isn't kept.
    }
};
