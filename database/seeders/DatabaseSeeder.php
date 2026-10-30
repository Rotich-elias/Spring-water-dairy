<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(LookupSeeder::class);
        $this->call(BenchmarkSeeder::class);

        // Initial Owner account from environment variables. Keep this idempotent
        // so seeding can be re-run safely after a redeploy; an existing Owner's
        // password is never overwritten.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $ownerEmail = env('APP_OWNER_EMAIL', 'owner@springwaterdairy.local');
        $ownerPassword = env('APP_OWNER_PASSWORD');

        if (app()->environment('production')
            && (empty($ownerPassword) || $ownerPassword === 'change-me-immediately')) {
            throw new \RuntimeException('Set a strong APP_OWNER_PASSWORD before seeding in production.');
        }

        $owner = User::firstOrCreate(
            ['email' => $ownerEmail],
            [
                'name' => 'Owner',
                'password' => bcrypt($ownerPassword ?: 'change-me-immediately'),
            ]
        );
        $role = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'api']
        );
        $owner->assignRole($role);
    }
}
