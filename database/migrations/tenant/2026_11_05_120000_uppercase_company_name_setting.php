<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The workspace's own company name (Settings → General / Branding) is stored in
 * capitals from now on (TenantSettings::put); bring the saved one into line.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        DB::table('settings')->where('key', 'company_name')->whereNotNull('value')
            ->update(['value' => DB::raw('UPPER(TRIM(value))')]);
    }

    public function down(): void
    {
        // The original capitalisation isn't kept.
    }
};
