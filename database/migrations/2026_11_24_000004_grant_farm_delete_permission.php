<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('farms.delete', 'api');
        $role = Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'api']);

        if (! $role->hasPermissionTo($permission)) {
            $role->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::query()->where('name', 'Platform Admin')->where('guard_name', 'api')->first();

        if ($role && $role->hasPermissionTo('farms.delete')) {
            $role->revokePermissionTo('farms.delete');
        }

        Permission::query()->where('name', 'farms.delete')->where('guard_name', 'api')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
