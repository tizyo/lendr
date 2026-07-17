<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * All platform permissions, grouped by module.
     * Format: 'module.action'
     */
    private array $permissions = [
        // Borrowers
        'borrowers.view',
        'borrowers.create',
        'borrowers.edit',
        'borrowers.delete',
        'borrowers.blacklist',
        'borrowers.export',

        // KYC
        'kyc.view',
        'kyc.review',
        'kyc.upload',

        // Loans
        'loans.view',
        'loans.create',
        'loans.edit',
        'loans.approve',
        'loans.disburse',
        'loans.deny',
        'loans.freeze',
        'loans.write_off',
        'loans.delete',
        'loans.export',

        // Loan Products
        'loan_products.view',
        'loan_products.create',
        'loan_products.edit',
        'loan_products.delete',

        // Payments
        'payments.view',
        'payments.create',
        'payments.delete',
        'payments.export',

        // Fund Management
        'funds.view',
        'funds.deposit',
        'funds.approve_deposit',
        'funds.export',

        // Expenses
        'expenses.view',
        'expenses.create',
        'expenses.edit',
        'expenses.delete',
        'expenses.approve',
        'expenses.export',

        // Reports
        'reports.view',
        'reports.export',

        // Staff
        'staff.view',
        'staff.create',
        'staff.edit',
        'staff.delete',
        'staff.reset_password',

        // Settings
        'settings.view',
        'settings.edit',

        // Notifications
        'notifications.broadcast',
    ];

    /**
     * Role → permissions matrix.
     */
    private array $rolePermissions = [
        UserRole::SuperAdmin->value => '*', // all permissions

        UserRole::BranchManager->value => [
            'borrowers.view', 'borrowers.create', 'borrowers.edit', 'borrowers.blacklist', 'borrowers.export',
            'kyc.view', 'kyc.review', 'kyc.upload',
            'loans.view', 'loans.create', 'loans.edit', 'loans.approve', 'loans.disburse', 'loans.deny', 'loans.freeze', 'loans.export',
            'loan_products.view',
            'payments.view', 'payments.create', 'payments.export',
            'funds.view', 'funds.deposit', 'funds.approve_deposit', 'funds.export',
            'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.approve', 'expenses.export',
            'reports.view', 'reports.export',
            'staff.view',
            'settings.view',
        ],

        UserRole::LoanOfficer->value => [
            'borrowers.view', 'borrowers.create', 'borrowers.edit', 'borrowers.export',
            'kyc.view', 'kyc.upload',
            'loans.view', 'loans.create', 'loans.edit', 'loans.export',
            'loan_products.view',
            'payments.view', 'payments.create',
            'funds.view',
            'expenses.view', 'expenses.create', 'expenses.edit',
            'reports.view',
        ],

        UserRole::Cashier->value => [
            'borrowers.view',
            'kyc.view',
            'loans.view',
            'payments.view', 'payments.create', 'payments.export',
            'funds.view', 'funds.deposit',
            'reports.view',
        ],

        UserRole::Accountant->value => [
            'borrowers.view', 'borrowers.export',
            'loans.view', 'loans.export',
            'payments.view', 'payments.export',
            'funds.view', 'funds.deposit', 'funds.approve_deposit', 'funds.export',
            'expenses.view', 'expenses.approve', 'expenses.export',
            'reports.view', 'reports.export',
        ],

        UserRole::Auditor->value => [
            'borrowers.view', 'borrowers.export',
            'kyc.view',
            'loans.view', 'loans.export',
            'payments.view', 'payments.export',
            'funds.view', 'funds.export',
            'expenses.view', 'expenses.export',
            'reports.view', 'reports.export',
        ],
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create all permissions
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Create roles and assign permissions
        foreach ($this->rolePermissions as $roleName => $perms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if ($perms === '*') {
                $role->syncPermissions(Permission::all());
            } else {
                $role->syncPermissions($perms);
            }
        }
    }
}
