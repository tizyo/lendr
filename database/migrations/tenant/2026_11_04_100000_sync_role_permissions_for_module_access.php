<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Module permissions (savings, investors, accounting, collections, risk, approvals,
 * sales, commissions, marketplace, branches, insurance, compliance, CRB,
 * integrations, audit log, billing) that every staff route now checks.
 *
 * Existing workspaces were seeded before these existed, so without this their
 * roles would hold none of them and every module would refuse everyone.
 * Roles are a fixed matrix (there's no role editor), so re-syncing is safe and
 * idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        (new RolesAndPermissionsSeeder)->run();
    }

    public function down(): void
    {
        // Permissions are additive; leaving them in place is harmless.
    }
};
