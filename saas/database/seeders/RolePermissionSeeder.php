<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * The fixed authorization vocabulary.
 *
 * Two permissions deserve note because they are the ones that move money:
 *
 *   payments.create   held by every role that operates a storefront
 *   refunds.create    deliberately NOT granted to `employee`
 *
 * Refunds send money outward and are the classic insider-fraud path, so they
 * require a role a person cannot assign to themselves.
 */
class RolePermissionSeeder extends Seeder
{
    private const PERMISSIONS = [
        // Websites
        'websites.view.own' => ['websites', 'View websites they own'],
        'websites.view.all' => ['websites', 'View every website in the tenant'],
        'websites.create' => ['websites', 'Register a new website'],
        'websites.update' => ['websites', 'Change website settings'],
        'websites.delete' => ['websites', 'Remove a website'],
        'websites.assign_stripe' => ['websites', 'Choose which Stripe account a website uses'],

        // Stripe accounts
        'stripe.view' => ['stripe', 'See Stripe accounts and their masked keys'],
        'stripe.create' => ['stripe', 'Add a Stripe account'],
        'stripe.update' => ['stripe', 'Edit or rotate Stripe credentials'],
        'stripe.delete' => ['stripe', 'Remove a Stripe account'],
        'stripe.assign' => ['stripe', 'Grant a Stripe account to a user'],

        // Payments
        'payments.create' => ['payments', 'Create payments through an assigned account'],
        'payments.view.own' => ['payments', 'View payments from their own websites'],
        'payments.view.all' => ['payments', 'View every payment in the tenant'],

        // Refunds and disputes
        'refunds.create' => ['refunds', 'Issue a refund'],
        'refunds.view' => ['refunds', 'View refunds'],
        'disputes.view' => ['disputes', 'View disputes'],
        'disputes.respond' => ['disputes', 'Submit dispute evidence'],

        // People
        'users.view' => ['users', 'View users'],
        'users.create' => ['users', 'Invite a user'],
        'users.update' => ['users', 'Edit a user or change their role'],
        'users.delete' => ['users', 'Deactivate a user'],

        // Reporting and oversight
        'reports.view' => ['reports', 'View reports and exports'],
        'audit.view' => ['audit', 'Read the audit log'],
        'settings.manage' => ['settings', 'Change tenant settings'],

        // Platform-only
        'platform.tenants.manage' => ['platform', 'Create and suspend tenants'],
        'platform.impersonate' => ['platform', 'Act as a tenant user for support'],
    ];

    private const ROLES = [
        'platform_admin' => [
            'name' => 'Platform Administrator',
            'description' => 'Operates the platform itself. Sits above tenancy.',
            'is_platform_role' => true,
            'permissions' => '*',
        ],
        'tenant_owner' => [
            'name' => 'Tenant Owner',
            'description' => 'Full control of one tenant, including Stripe credentials.',
            'is_platform_role' => false,
            'permissions' => [
                'websites.view.all', 'websites.view.own', 'websites.create', 'websites.update',
                'websites.delete', 'websites.assign_stripe',
                'stripe.view', 'stripe.create', 'stripe.update', 'stripe.delete', 'stripe.assign',
                'payments.create', 'payments.view.all', 'payments.view.own',
                'refunds.create', 'refunds.view', 'disputes.view', 'disputes.respond',
                'users.view', 'users.create', 'users.update', 'users.delete',
                'reports.view', 'audit.view', 'settings.manage',
            ],
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'Runs day-to-day operations but cannot see or add Stripe credentials.',
            'is_platform_role' => false,
            'permissions' => [
                'websites.view.all', 'websites.view.own', 'websites.create', 'websites.update',
                'websites.assign_stripe',
                'stripe.view',
                'payments.create', 'payments.view.all', 'payments.view.own',
                'refunds.create', 'refunds.view', 'disputes.view', 'disputes.respond',
                'users.view',
                'reports.view', 'audit.view',
            ],
        ],
        'employee' => [
            'name' => 'Employee',
            'description' => 'Operates their own websites. Cannot refund.',
            'is_platform_role' => false,
            'permissions' => [
                'websites.view.own', 'websites.create', 'websites.update', 'websites.assign_stripe',
                'payments.create', 'payments.view.own',
                'refunds.view', 'disputes.view',
                'reports.view',
            ],
        ],
        'support' => [
            'name' => 'Support',
            'description' => 'Reads everything, changes nothing except dispute evidence.',
            'is_platform_role' => false,
            'permissions' => [
                'websites.view.all', 'payments.view.all', 'refunds.view',
                'disputes.view', 'disputes.respond', 'reports.view',
            ],
        ],
        'read_only' => [
            'name' => 'Read Only',
            'description' => 'Reporting access with no ability to act.',
            'is_platform_role' => false,
            'permissions' => [
                'websites.view.all', 'payments.view.all', 'refunds.view', 'reports.view',
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::PERMISSIONS as $key => [$group, $description]) {
            Permission::updateOrCreate(
                ['key' => $key],
                ['group' => $group, 'description' => $description]
            );
        }

        foreach (self::ROLES as $key => $definition) {
            $role = Role::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_platform_role' => $definition['is_platform_role'],
                ]
            );

            $permissions = $definition['permissions'] === '*'
                ? array_keys(self::PERMISSIONS)
                : $definition['permissions'];

            $role->permissions()->sync($permissions);
        }
    }
}
