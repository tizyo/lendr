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

        // Savings: view accounts / open and change accounts / post deposits and withdrawals
        'savings.view',
        'savings.manage',
        'savings.transact',

        // Investors, allocations and dividends
        'investors.view',
        'investors.manage',

        // Accounting: GL, statements, accruals, provisioning, tax, reconciliation, FX
        'accounting.view',
        'accounting.manage',

        // Collections: cases, escalation, penalties, field visits
        'collections.view',
        'collections.manage',
        'collections.configure', // escalation rules

        // Risk: risk policies, auto-decision rules, risk flags
        'risk.view',
        'risk.manage',

        // Approval workflows: see requests / approve or reject / configure workflows
        'approvals.view',
        'approvals.act',
        'approvals.manage',

        // Sales: leads, campaigns, targets, agents, loan offers
        'sales.view',
        'sales.manage',
        'sales.targets',

        // Staff and agent commissions
        'commissions.view',
        'commissions.approve',

        // Marketplaces: repo items, featured items, hot deals, product listings
        'marketplace.view',
        'marketplace.manage',

        // Branches
        'branches.view',
        'branches.manage',

        // Loan insurance
        'insurance.view',
        'insurance.manage',

        // Compliance calendar and regulatory reports
        'compliance.view',
        'compliance.manage',

        // Credit reference bureau
        'crb.view',
        'crb.check',

        // API clients and outbound webhooks
        'integrations.manage',

        // Audit trail
        'audit_log.view',

        // Workspace subscription
        'billing.manage',
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
            'savings.view', 'savings.manage', 'savings.transact',
            'investors.view',
            'accounting.view',
            'collections.view', 'collections.manage', 'collections.configure',
            'risk.view',
            'approvals.view', 'approvals.act',
            'sales.view', 'sales.manage', 'sales.targets',
            'commissions.view', 'commissions.approve',
            'marketplace.view', 'marketplace.manage',
            'branches.view',
            'insurance.view', 'insurance.manage',
            'compliance.view',
            'crb.view', 'crb.check',
            'audit_log.view',
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
            'savings.view', 'savings.manage',
            'collections.view', 'collections.manage',
            'risk.view',
            'approvals.view',
            'sales.view', 'sales.manage',
            'commissions.view',
            'marketplace.view',
            'branches.view',
            'insurance.view',
            'crb.view', 'crb.check',
        ],

        UserRole::Cashier->value => [
            'borrowers.view',
            'kyc.view',
            'loans.view',
            'payments.view', 'payments.create', 'payments.export',
            'funds.view', 'funds.deposit',
            'reports.view',
            'savings.view', 'savings.transact',
            'collections.view',
            'branches.view',
            'insurance.view',
        ],

        UserRole::Accountant->value => [
            'borrowers.view', 'borrowers.export',
            'loans.view', 'loans.export',
            'payments.view', 'payments.export',
            'funds.view', 'funds.deposit', 'funds.approve_deposit', 'funds.export',
            'expenses.view', 'expenses.approve', 'expenses.export',
            'reports.view', 'reports.export',
            'savings.view',
            'investors.view', 'investors.manage',
            'accounting.view', 'accounting.manage',
            'collections.view',
            'approvals.view', 'approvals.act',
            'commissions.view', 'commissions.approve',
            'branches.view',
            'insurance.view',
            'compliance.view', 'compliance.manage',
            'crb.view',
            'audit_log.view',
        ],

        UserRole::Auditor->value => [
            'borrowers.view', 'borrowers.export',
            'kyc.view',
            'loans.view', 'loans.export',
            'payments.view', 'payments.export',
            'funds.view', 'funds.export',
            'expenses.view', 'expenses.export',
            'reports.view', 'reports.export',
            'savings.view',
            'investors.view',
            'accounting.view',
            'collections.view',
            'risk.view',
            'approvals.view',
            'sales.view',
            'commissions.view',
            'marketplace.view',
            'branches.view',
            'insurance.view',
            'compliance.view',
            'crb.view',
            'audit_log.view',
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
