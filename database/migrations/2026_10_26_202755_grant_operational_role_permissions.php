<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Operational roles need read-only access to reference data (categories,
     * pens, breeds, statuses) and, for milkers, health records so withholding
     * information is visible. Lookup editing stays Owner/Manager only.
     *
     * @var array<string, list<string>>
     */
    private array $permissions = [
        'lookups.view' => ['Milker', 'AI Tech', 'Vet', 'Viewer'],
        'health.view' => ['Milker'],
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName => $roles) {
            $permission = Permission::findOrCreate($permissionName, 'api');

            Role::query()
                ->whereIn('name', $roles)
                ->where('guard_name', 'api')
                ->get()
                ->each(function (Role $role) use ($permission) {
                    if (! $role->hasPermissionTo($permission)) {
                        $role->givePermissionTo($permission);
                    }
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($this->permissions as $permissionName => $roles) {
            Role::query()
                ->whereIn('name', $roles)
                ->where('guard_name', 'api')
                ->get()
                ->each(function (Role $role) use ($permissionName) {
                    $role->revokePermissionTo($permissionName);
                });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
