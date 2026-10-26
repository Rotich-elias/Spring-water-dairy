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

        // Initial Owner account from environment variables.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $owner = User::factory()->create([
            'name' => 'Owner',
            'email' => env('APP_OWNER_EMAIL', 'owner@springwaterdairy.local'),
            'password' => bcrypt(env('APP_OWNER_PASSWORD', 'change-me-immediately')),
        ]);
        $role = Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'api']
        );
        $owner->assignRole($role);
    }
}
