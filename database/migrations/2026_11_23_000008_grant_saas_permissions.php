<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * All permissions available to the Owner, including the existing ones
     * granted by earlier migrations and the RoleSeeder.
     *
     * @var list<string>
     */
    private array $ownerPermissions = [
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
        'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
        'appearance.view', 'appearance.update',
        'billing.view', 'billing.manage',
        'plans.view',
    ];

    /** @var list<string> */
    private array $newPermissions = [
        'tenants.view', 'tenants.create', 'tenants.update', 'tenants.delete',
        'appearance.view', 'appearance.update',
        'billing.view', 'billing.manage',
        'plans.view',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->newPermissions as $name) {
            Permission::findOrCreate($name, 'api');
        }

        $this->grant('Owner', $this->ownerPermissions);
        $this->grant('Admin', array_values(array_diff($this->newPermissions, ['tenants.delete', 'billing.manage'])));
        $this->grant('Manager', ['tenants.view', 'appearance.view', 'billing.view', 'plans.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->revoke('Owner', $this->newPermissions);
        $this->revoke('Admin', $this->newPermissions);
        $this->revoke('Manager', ['tenants.view', 'appearance.view', 'billing.view', 'plans.view']);

        foreach ($this->newPermissions as $name) {
            Permission::query()->where('name', $name)->where('guard_name', 'api')->delete();
        }

        Role::query()->where('name', 'Admin')->where('guard_name', 'api')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function grant(string $roleName, array $permissions): void
    {
        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'api']);

        foreach ($permissions as $name) {
            $permission = Permission::findOrCreate($name, 'api');

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }
    }

    /**
     * @param  list<string>  $permissions
     */
    private function revoke(string $roleName, array $permissions): void
    {
        $role = Role::query()->where('name', $roleName)->where('guard_name', 'api')->first();

        if (! $role) {
            return;
        }

        foreach ($permissions as $name) {
            if ($role->hasPermissionTo($name)) {
                $role->revokePermissionTo($name);
            }
        }
    }
};
