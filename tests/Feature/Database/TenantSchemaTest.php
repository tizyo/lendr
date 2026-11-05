<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The rest of the suite runs central and workspace migrations into ONE database,
 * so a table that only the central database has looks present to every test —
 * that hid "forgot password" failing for every workspace (password_reset_tokens
 * existed centrally only) and, earlier, the missing token table. Here the
 * workspace migrations run alone, into an empty database, the way production
 * builds each workspace.
 */

it('gives every workspace the tables its own requests write to', function () {
    config(['database.connections.tenant_schema_check' => [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
    ]]);

    try {
        Artisan::call('migrate', ['--database' => 'tenant_schema_check', '--path' => 'database/migrations/tenant', '--force' => true]);

        $schema = Schema::connection('tenant_schema_check');

        foreach ([
            'users',                  // staff sign-in
            'password_reset_tokens',  // forgot password (the reset broker writes here)
            'personal_access_tokens', // staff API / app tokens
            'activity_log',           // audit trail, sign-ins, screen visits
            'settings',               // TenantSettings
            'roles', 'permissions',   // spatie permission
        ] as $table) {
            expect($schema->hasTable($table))->toBeTrue("The workspace database has no `{$table}` table.");
        }

        expect($schema->hasColumns('users', ['last_login_at', 'last_seen_at', 'password_changed_at']))->toBeTrue();
    } finally {
        DB::purge('tenant_schema_check');
    }
});
