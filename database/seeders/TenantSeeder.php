<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Tenant\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        // Run RBAC seeder first
        $this->call(RolesAndPermissionsSeeder::class);

        // Create super admin
        $admin = User::firstOrCreate(
            ['email' => 'admin@vozara.lendr.app'],
            [
                'name'     => 'System Admin',
                'email'    => 'admin@vozara.lendr.app',
                'username' => 'admin',
                'password' => Hash::make('Admin@12345!'),
                'role'     => UserRole::SuperAdmin,
                'is_active' => true,
                'force_password_reset' => true,
            ]
        );

        $admin->assignRole(UserRole::SuperAdmin->value);
    }
}
