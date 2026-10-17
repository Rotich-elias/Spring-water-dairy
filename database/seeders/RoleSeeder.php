<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

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
                'alerts.view', 'alerts.edit', 'notifications.view', 'tasks.view',
                'system.health', 'setup.view', 'setup.complete',
            ],
            'Manager' => [
                'users.view',
                'lookups.view', 'lookups.edit',
                'animals.view', 'animals.create', 'animals.update',
                'milk.view', 'milk.create', 'milk.update',
                'breeding.view', 'breeding.create', 'breeding.update',
                'calves.view', 'calves.create', 'calves.update',
                'health.view', 'health.create', 'health.update',
                'dashboard.view', 'reports.view', 'audit.view',
                'alerts.view', 'alerts.edit', 'notifications.view', 'tasks.view',
            ],
            'Milker' => [
                'animals.view',
                'milk.view', 'milk.create', 'milk.update',
                'calves.view', 'calves.create',
                // health.create intentionally omitted: Milker only observes, doesn't create treatments
                'dashboard.view',
                'notifications.view', 'tasks.view',
            ],
            'AI Tech' => [
                'animals.view',
                'breeding.view', 'breeding.create', 'breeding.update',
                'calves.view',
                'dashboard.view',
                'notifications.view', 'tasks.view',
            ],
            'Vet' => [
                'animals.view',
                'breeding.view',
                'health.view', 'health.create', 'health.update', 'health.delete',
                'dashboard.view',
                'notifications.view', 'tasks.view',
            ],
            'Viewer' => [
                'animals.view',
                'milk.view',
                'breeding.view',
                'calves.view',
                'health.view',
                'dashboard.view',
                'notifications.view',
            ],
        ];

        foreach ($roles as $roleName => $permissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);

            $permissionModels = collect($permissions)->map(fn (string $name) =>
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'api'])
            )->all();

            $role->syncPermissions($permissionModels);
        }
    }
}
