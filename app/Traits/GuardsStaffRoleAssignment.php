<?php

namespace App\Traits;

use App\Enums\UserRole;

trait GuardsStaffRoleAssignment
{
    /**
     * A staff member can only grant roles at or below their own privilege
     * level — SuperAdmin is the only role that can grant SuperAdmin.
     */
    private function assertCanGrantRole(UserRole $target): void
    {
        $acting = auth()->user()->role;

        if (! $acting instanceof UserRole || $target->level() > $acting->level()) {
            abort(403, 'You cannot assign a role above your own privilege level.');
        }
    }
}
