<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(LookupSeeder::class);
        $this->call(BenchmarkSeeder::class);

        // Initial Owner account from environment variables.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $owner = \App\Models\User::factory()->create([
            'name' => 'Owner',
            'email' => env('APP_OWNER_EMAIL', 'owner@springwaterdairy.local'),
            'password' => bcrypt(env('APP_OWNER_PASSWORD', 'change-me-immediately')),
        ]);
        $role = \Spatie\Permission\Models\Role::firstOrCreate(
            ['name' => 'Owner', 'guard_name' => 'api']
        );
        $owner->assignRole($role);
    }
}
