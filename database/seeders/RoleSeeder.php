<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Permissions introduced with multi-tenant farms, billing and appearance.
     *
     * @var list<string>
     */
    private array $saasPermissions = [
        'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
        'appearance.view', 'appearance.update',
        'billing.view', 'billing.manage',
        'plans.view',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'Owner' => [
                'users.view', 'users.create', 'users.update', 'users.delete',
                'settings.view', 'settings.update',
                'lookups.view', 'lookups.edit',
                'animals.view', 'animals.create', 'animals.update', 'animals.delete',
                'milk.view', 'milk.create', 'milk.update',
                'breeding.view', 'breeding.create', 'breeding.update',
                'calves.view', 'calves.create', 'calves.update',
                'health.view', 'health.create', 'health.update', 'health.delete',
                'dashboard.view', 'reports.view', 'audit.view',
                'alerts.view', 'alerts.create', 'alerts.edit', 'alerts.delete',
                'notifications.view', 'notifications.create', 'notifications.update', 'notifications.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
                'system.health', 'setup.view', 'setup.complete',
            ],
            'Admin' => [],
            'Manager' => [
                'users.view',
                'lookups.view', 'lookups.edit',
                'animals.view', 'animals.create', 'animals.update',
                'milk.view', 'milk.create', 'milk.update',
                'breeding.view', 'breeding.create', 'breeding.update',
                'calves.view', 'calves.create', 'calves.update',
                'health.view', 'health.create', 'health.update',
                'dashboard.view', 'reports.view', 'audit.view',
                'alerts.view', 'alerts.create', 'alerts.edit', 'alerts.delete',
                'notifications.view', 'notifications.create', 'notifications.update', 'notifications.delete',
                'tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete',
            ],
            'Milker' => [
                'animals.view',
                'lookups.view',
                'milk.view', 'milk.create', 'milk.update',
                'calves.view', 'calves.create',
                // Milker observes health/withholding but cannot record treatments.
                'health.view',
                'dashboard.view',
                'notifications.view', 'tasks.view', 'tasks.complete',
            ],
            'AI Tech' => [
                'animals.view',
                'lookups.view',
                'breeding.view', 'breeding.create', 'breeding.update',
                'calves.view',
                'dashboard.view',
                'notifications.view', 'tasks.view',
            ],
            'Vet' => [
                'animals.view',
                'lookups.view',
                'breeding.view',
                'health.view', 'health.create', 'health.update', 'health.delete',
                'dashboard.view',
                'notifications.view', 'tasks.view',
            ],
            'Viewer' => [
                'animals.view',
                'lookups.view',
                'milk.view',
                'breeding.view',
                'calves.view',
                'health.view',
                'dashboard.view',
                'notifications.view',
            ],
        ];

        $roles['Owner'] = array_merge($roles['Owner'], $this->saasPermissions);
        $roles['Admin'] = array_values(array_diff($this->saasPermissions, ['tenants.delete', 'billing.manage']));
        $roles['Manager'] = array_merge($roles['Manager'], [
            'tenants.view', 'appearance.view', 'billing.view', 'plans.view',
        ]);

        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);

            $permissionModels = collect($permissions)->map(fn (string $name) => Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api'])
            )->all();

            $role->syncPermissions($permissionModels);
        }
    }
}
