<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Settings\Services\SettingService;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Lookup;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\LookupSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantService
{
    public function __construct(
        private TenantContext $context,
        private SettingService $settings,
    ) {}

    /**
     * Fetch (or create) the built-in farm and make it the active tenant.
     */
    public function ensureDefaultTenant(): Tenant
    {
        $slug = (string) config('plans.default_tenant.slug', 'spring-water-dairy');

        $tenant = Tenant::query()->where('slug', $slug)->first();

        if (! $tenant) {
            $tenant = Tenant::create([
                'name' => (string) config('plans.default_tenant.name', 'Spring Water Dairy'),
                'slug' => $slug,
                'status' => 'active',
            ]);

            $this->seedDefaults($tenant);
            $this->ensureStarterSubscription($tenant);
        }

        $this->context->set($tenant);

        $ownerEmail = (string) config('plans.default_tenant.owner_email', 'owner@springwaterdairy.local');

        User::query()
            ->whereHas('roles', fn ($query) => $query
                ->where('name', 'Owner')
                ->where('guard_name', 'api'))
            ->orWhere('email', $ownerEmail)
            ->get()
            ->each(function (User $user) use ($tenant): void {
                $tenant->users()->syncWithoutDetaching([
                    $user->id => ['role' => 'Owner', 'is_owner' => true],
                ]);

                $user->forceFill(['current_tenant_id' => $tenant->id])->save();
            });

        return $tenant;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $owner): Tenant
    {
        $name = (string) ($data['name'] ?? 'New Farm');

        $tenant = DB::transaction(function () use ($data, $name, $owner): Tenant {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? Str::slug($name))),
                'status' => (string) ($data['status'] ?? 'active'),
                'owner_id' => $owner->id,
            ]);

            $this->context->set($tenant);

            $tenant->users()->attach($owner->id, [
                'role' => 'Owner',
                'is_owner' => true,
            ]);

            if ($owner->current_tenant_id === null) {
                $owner->forceFill(['current_tenant_id' => $tenant->id])->save();
            }

            $this->seedDefaults($tenant);
            $this->ensureStarterSubscription($tenant);

            return $tenant;
        });

        return $tenant;
    }

    /**
     * Provision a farm for an explicitly supplied owner. The caller's active
     * tenant and current_tenant_id are deliberately left untouched.
     *
     * @param  array<string, mixed>  $data
     */
    public function provisionFor(array $data, User $owner): Tenant
    {
        return DB::transaction(function () use ($data, $owner): Tenant {
            $name = (string) ($data['name'] ?? 'New Farm');

            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $this->uniqueSlug((string) ($data['slug'] ?? Str::slug($name))),
                'status' => (string) ($data['status'] ?? 'active'),
                'owner_id' => $owner->id,
            ]);

            $tenant->users()->attach($owner->id, [
                'role' => 'Owner',
                'is_owner' => true,
            ]);

            $previous = $this->context->tenant();
            $this->context->set($tenant);

            try {
                $this->seedDefaults($tenant);
                $this->ensureStarterSubscription($tenant);
            } finally {
                if ($previous) {
                    $this->context->set($previous);
                } else {
                    $this->context->clear();
                }
            }

            return $tenant;
        });
    }

    /**
     * Seed a tenant's settings and reference data without leaking the tenant
     * into the surrounding execution lifecycle.
     */
    public function seedDefaults(Tenant $tenant): void
    {
        $previous = $this->context->tenant();
        $this->context->set($tenant);

        try {
            foreach (SettingService::defaults() as $key => [$value, $type, $group, $isPublic]) {
                Setting::firstOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'type' => $type, 'group' => $group, 'is_public' => $isPublic]
                );
            }

            foreach (LookupSeeder::rows() as $type => $rows) {
                foreach ($rows as $row) {
                    Lookup::firstOrCreate(
                        ['type' => $type, 'name' => $row['name']],
                        ['sort_order' => $row['sort_order'], 'active' => true]
                    );
                }
            }
        } finally {
            if ($previous) {
                $this->context->set($previous);
            } else {
                $this->context->clear();
            }
        }
    }

    public function switch(User $user, Tenant $tenant): void
    {
        if (! $this->memberships($user)->contains(fn (Tenant $membership) => $membership->is($tenant))) {
            throw new AuthorizationException('You are not a member of this farm.');
        }

        $user->forceFill(['current_tenant_id' => $tenant->id])->save();

        $this->context->set($tenant);
    }

    /**
     * @return Collection<int, Tenant>
     */
    public function memberships(User $user): Collection
    {
        return $user->tenants()->orderBy('name')->get();
    }

    private function uniqueSlug(string $candidate): string
    {
        $base = Str::slug($candidate) ?: 'farm';
        $slug = $base;
        $suffix = 2;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function ensureStarterSubscription(Tenant $tenant): Subscription
    {
        $plan = Plan::query()->where('code', 'starter')->firstOrFail();

        return Subscription::firstOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]
        );
    }
}
