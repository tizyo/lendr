<?php

namespace Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Extends RefreshDatabase to also run tenant schema migrations.
 *
 * PHP trait methods override parent class methods, so defining migrateDatabases()
 * in a trait that includes RefreshDatabase ensures our version runs.
 */
trait RunsTenantMigrations
{
    use RefreshDatabase;

    protected function migrateDatabases()
    {
        // Root migrations: users, cache, jobs, tenancy, permissions, billing, etc.
        $this->artisan('migrate:fresh', $this->migrateFreshUsing());

        // Tenant-schema migrations: borrowers, loans, kyc_documents, etc.
        $this->artisan('migrate', ['--path' => 'database/migrations/tenant']);

        // Landlord-specific migrations: landlord_users, etc.
        $this->artisan('migrate', ['--path' => 'database/migrations/landlord']);

        // Seed roles/permissions so any factory-created User with a `role`
        // gets real, enforceable permissions via User::booted()'s sync hook,
        // matching what tenant provisioning does outside tests.
        (new \Database\Seeders\RolesAndPermissionsSeeder)->run();
    }
}
