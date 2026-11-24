<?php

namespace Database\Seeders;

use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(PlanSeeder::class);

        // Establish the default farm and its context before seeding scoped data.
        $tenants = app(TenantService::class);
        $tenants->ensureDefaultTenant();

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

        // Attach the Owner now that the account exists; safe to re-run.
        $tenants->ensureDefaultTenant();

        // Global settings have no tenant scope and back the public site.
        $this->call(SiteSettingSeeder::class);

        // Created after the last tenant bootstrap so the platform admin is
        // never attached to a farm or assigned a current tenant. Re-seeding
        // never overwrites an existing account's password.
        $platformAdminEmail = (string) config('site.platform_admin_email');
        $platformAdminPassword = (string) config('site.platform_admin_password');

        $platformAdmin = User::firstOrCreate(
            ['email' => $platformAdminEmail],
            [
                'name' => 'Platform Admin',
                'password' => bcrypt($platformAdminPassword),
            ],
        );
        $platformAdmin->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'api'],
        ));

        // Role changes must be visible to the next authorization check.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
