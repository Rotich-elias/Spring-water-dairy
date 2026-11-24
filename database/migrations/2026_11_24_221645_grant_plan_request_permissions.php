<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /** @var list<string> */
    private array $permissions = [
        'plan_requests.view',
        'plan_requests.manage',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'api']);

        foreach ($this->permissions as $name) {
            $permission = Permission::findOrCreate($name, 'api');

            if (! $role->hasPermissionTo($permission)) {
                $role->givePermissionTo($permission);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()
            ->where('name', 'Platform Admin')
            ->where('guard_name', 'api')
            ->first();

        foreach ($this->permissions as $name) {
            $permission = Permission::query()
                ->where('name', $name)
                ->where('guard_name', 'api')
                ->first();

            if ($permission !== null) {
                if ($role !== null && $role->hasPermissionTo($permission)) {
                    $role->revokePermissionTo($permission);
                }

                $permission->delete();
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
