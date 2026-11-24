<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TENANT_TABLES = [
        'settings',
        'lookups',
        'audit_logs',
        'animals',
        'milk_records',
        'bulk_tank_records',
        'lactations',
        'sync_states',
        'heats',
        'services',
        'pregnancy_checks',
        'calvings',
        'semen_straws',
        'weights',
        'calf_records',
        'treatments',
        'vaccinations',
        'alerts',
        'notifications',
        'alert_rules',
        'tasks',
        'images',
    ];

    public function up(): void
    {
        $this->seedPlans();

        $slug = (string) config('plans.default_tenant.slug', 'spring-water-dairy');
        $tenantId = DB::table('tenants')->where('slug', $slug)->value('id');

        if (! $tenantId) {
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => (string) config('plans.default_tenant.name', 'Spring Water Dairy'),
                'slug' => $slug,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $starterPlanId = DB::table('plans')->where('code', 'starter')->value('id');

        if ($starterPlanId && ! DB::table('subscriptions')->where('tenant_id', $tenantId)->exists()) {
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId,
                'plan_id' => $starterPlanId,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach (self::TENANT_TABLES as $tableName) {
            DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        }

        $ownerEmail = (string) config('plans.default_tenant.owner_email', 'owner@springwaterdairy.local');

        foreach (DB::table('users')->select(['id', 'email'])->get() as $user) {
            $role = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_id', $user->id)
                ->whereIn('model_has_roles.model_type', ['App\\Models\\User', 'App\\User'])
                ->orderBy('roles.id')
                ->value('roles.name');

            DB::table('tenant_user')->insertOrIgnore([
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'role' => $role ?: ($user->email === $ownerEmail ? 'Owner' : 'Viewer'),
                'is_owner' => $role === 'Owner' || $user->email === $ownerEmail,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('users')->where('id', $user->id)->update(['current_tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        $slug = (string) config('plans.default_tenant.slug', 'spring-water-dairy');
        $tenantId = DB::table('tenants')->where('slug', $slug)->value('id');

        if (! $tenantId) {
            return;
        }

        DB::table('subscriptions')->where('tenant_id', $tenantId)->delete();
        DB::table('tenant_user')->where('tenant_id', $tenantId)->delete();
        DB::table('users')->where('current_tenant_id', $tenantId)->update(['current_tenant_id' => null]);

        foreach (self::TENANT_TABLES as $tableName) {
            DB::table($tableName)->where('tenant_id', $tenantId)->update(['tenant_id' => null]);
        }

        DB::table('tenants')->where('id', $tenantId)->delete();
    }

    private function seedPlans(): void
    {
        $plans = [
            [
                'code' => 'starter',
                'name' => 'Starter',
                'description' => 'For small farms getting started with digital records.',
                'price_monthly' => 0,
                'limits' => ['animals' => 50, 'users' => 3, 'milk_records' => 3000, 'milk_records_per_month' => 3000, 'alert_rules' => 5],
                'sort_order' => 1,
            ],
            [
                'code' => 'growth',
                'name' => 'Growth',
                'description' => 'For growing herds with more people and records.',
                'price_monthly' => 7900,
                'limits' => ['animals' => 500, 'users' => 25, 'milk_records' => 50000, 'milk_records_per_month' => 50000, 'alert_rules' => 50],
                'sort_order' => 2,
            ],
            [
                'code' => 'scale',
                'name' => 'Scale',
                'description' => 'Unlimited records for large commercial operations.',
                'price_monthly' => 24900,
                'limits' => ['animals' => null, 'users' => null, 'milk_records' => null, 'milk_records_per_month' => null, 'alert_rules' => null],
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            if (DB::table('plans')->where('code', $plan['code'])->exists()) {
                continue;
            }

            DB::table('plans')->insert([
                'code' => $plan['code'],
                'name' => $plan['name'],
                'description' => $plan['description'],
                'price_monthly' => $plan['price_monthly'],
                'limits' => json_encode($plan['limits']),
                'is_active' => true,
                'sort_order' => $plan['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
