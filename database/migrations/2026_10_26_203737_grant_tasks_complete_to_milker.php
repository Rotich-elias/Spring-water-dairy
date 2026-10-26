<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Milkers can tick off tasks assigned to them and leave a completion note,
     * but cannot create, assign, edit or delete tasks.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate('tasks.complete', 'api');

        Role::query()
            ->where('name', 'Milker')
            ->where('guard_name', 'api')
            ->get()
            ->each(function (Role $role) use ($permission) {
                if (! $role->hasPermissionTo($permission)) {
                    $role->givePermissionTo($permission);
                }
            });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::query()
            ->where('name', 'Milker')
            ->where('guard_name', 'api')
            ->get()
            ->each(fn (Role $role) => $role->revokePermissionTo('tasks.complete'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
