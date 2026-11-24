<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\Impersonation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->adminToken = $this->authenticateAsPlatformAdmin();
    $this->ownerToken = $this->authenticateAsOwner();
});

test('platform admin lists farms with owner, plan and usage data', function () {
    $provision = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Usage Farm',
            'slug' => 'usage-farm',
            'owner_email' => 'usage-owner@example.com',
            'plan_code' => 'growth',
            'payment_reference' => 'USAGE-INVOICE-1',
            'payment_date' => '2026-11-20',
        ]);

    $provision->assertStatus(201);
    $tenantId = $provision->json('data.id');

    $context = app(TenantContext::class);
    $context->set(Tenant::findOrFail($tenantId));
    Animal::factory()->count(2)->create();
    $context->clear();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller/farms');

    $response->assertOk();
    $farm = collect($response->json('data'))->firstWhere('id', $tenantId);

    expect($farm)->not->toBeNull();
    expect($farm)->toHaveKeys([
        'id', 'name', 'slug', 'status', 'owner_id', 'owner_name', 'owner_email',
        'plan_code', 'plan_name', 'subscription_status', 'subscription_canceled_at',
        'subscription_current_period_end', 'usage', 'created_at',
    ]);
    expect($farm['owner_email'])->toBe('usage-owner@example.com');
    expect($farm['plan_code'])->toBe('growth');
    expect($farm['subscription_status'])->toBe('active');
    expect($farm['usage']['animals'])->toBe(2);
    expect($farm['usage']['users'])->toBe(1);
    expect($farm['usage']['milk_records'])->toBe(0);
    expect($farm['usage']['alert_rules'])->toBe(0);
});

test('platform admin provisions a farm for a different owner and returns a temporary password once', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'New Branch',
            'slug' => 'new-branch',
            'owner_email' => 'new-owner@example.com',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.name', 'New Branch')
        ->assertJsonPath('data.slug', 'new-branch')
        ->assertJsonPath('data.owner_email', 'new-owner@example.com')
        ->assertJsonPath('data.existing_owner', false);

    $temporaryPassword = $response->json('data.temporary_password');
    expect($temporaryPassword)->toBeString()->not->toBeEmpty();

    $owner = User::where('email', 'new-owner@example.com')->firstOrFail();
    expect(Hash::check($temporaryPassword, $owner->password))->toBeTrue();
    expect($owner->hasRole('Owner', 'api'))->toBeTrue();
    expect($owner->getAllPermissions()->pluck('name'))->toContain('billing.view');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'farm.owner_created',
        'auditable_type' => User::class,
        'auditable_id' => $owner->id,
    ]);

    $tenantId = $response->json('data.id');
    $owner->refresh();
    expect($tenantId)->toBeInt();
    expect((int) $owner->current_tenant_id)->not->toBe($tenantId);

    $membership = DB::table('tenant_user')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $owner->id)
        ->first();

    expect($membership?->role)->toBe('Owner');
    expect((bool) $membership?->is_owner)->toBeTrue();

    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();
    expect(DB::table('tenant_user')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $admin->id)
        ->exists())->toBeFalse();
});

test('platform admin provisions a farm for an existing owner and returns a rotated password', function () {
    $owner = User::factory()->create([
        'email' => 'existing-owner@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $owner->createToken('previous-session');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Existing Owner Farm',
            'owner_email' => $owner->email,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.owner_email', $owner->email)
        ->assertJsonPath('data.existing_owner', true);

    $temporaryPassword = $response->json('data.temporary_password');
    expect($temporaryPassword)->toBeString()->not->toBeEmpty();

    $owner->refresh();
    expect(Hash::check($temporaryPassword, $owner->password))->toBeTrue()
        ->and(Hash::check('old-password', $owner->password))->toBeFalse()
        ->and($owner->tokens()->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'farm.owner_password_reset',
        'auditable_type' => User::class,
        'auditable_id' => $owner->id,
    ]);

    $tenantId = $response->json('data.id');
    expect(DB::table('tenant_user')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $owner->id)
        ->exists())->toBeTrue();
});

test('paid farm provisioning records payment and creates an approved invoice request', function () {
    $this->travelTo('2026-11-20 10:00:00');

    try {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
            ->postJson('/api/v1/seller/farms', [
                'name' => 'Paid Branch',
                'slug' => 'paid-branch',
                'owner_email' => 'paid-owner@example.com',
                'plan_code' => 'growth',
                'payment_reference' => 'PAY-REF-123',
                'payment_date' => '2026-11-18',
            ])
            ->assertStatus(201);

        $tenantId = $response->json('data.id');
        $request = PlanChangeRequest::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        expect($request->status)->toBe('approved')
            ->and($request->plan_code)->toBe('growth')
            ->and($request->invoice_number)->toBe('INV-2026-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT))
            ->and($request->payment_reference)->toBe('PAY-REF-123')
            ->and($request->payment_date->toDateString())->toBe('2026-11-18');

        $subscription = Subscription::withoutGlobalScopes()->where('tenant_id', $tenantId)->firstOrFail();
        expect($subscription->plan->code)->toBe('growth')
            ->and($subscription->payment_status)->toBe('paid')
            ->and($subscription->last_payment_at->toDateString())->toBe('2026-11-18')
            ->and($subscription->current_period_end->toDateString())->toBe('2026-12-18');

        foreach ([
            'plan_request.requested',
            'plan_request.invoiced',
            'plan_request.payment_recorded',
            'plan_request.approved',
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', [
                'action' => $action,
                'auditable_type' => PlanChangeRequest::class,
                'auditable_id' => $request->id,
            ]);
        }
    } finally {
        $this->travelBack();
    }
});

test('paid farm provisioning requires payment reference and date', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Unpaid Branch',
            'slug' => 'unpaid-branch',
            'owner_email' => 'unpaid-owner@example.com',
            'plan_code' => 'growth',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.payment_reference.0', 'A payment reference is required for a paid plan.')
        ->assertJsonPath('error.fields.payment_date.0', 'A payment date is required for a paid plan.');

    expect(Tenant::where('slug', 'unpaid-branch')->exists())->toBeFalse();
});

test('free farm provisioning does not require payment details', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Free Branch',
            'slug' => 'free-branch',
            'owner_email' => 'free-owner@example.com',
            'plan_code' => 'starter',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.owner_email', 'free-owner@example.com');

    $tenant = Tenant::where('slug', 'free-branch')->firstOrFail();
    expect(PlanChangeRequest::withoutGlobalScopes()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('platform admin resets a farm owner password without sending email', function () {
    $owner = User::factory()->create([
        'email' => 'reset-owner@example.com',
        'password' => Hash::make('old-password'),
    ]);
    $owner->createToken('existing-session');
    $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/owner/reset-password')
        ->assertOk()
        ->assertJsonPath('data.owner_id', $owner->id)
        ->assertJsonPath('data.owner_email', $owner->email);

    $newPassword = $response->json('data.temporary_password');
    expect($newPassword)->toBeString()->not->toBeEmpty();

    $owner->refresh();
    expect(Hash::check($newPassword, $owner->password))->toBeTrue()
        ->and(Hash::check('old-password', $owner->password))->toBeFalse()
        ->and($owner->tokens()->count())->toBe(0);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'farm.owner_password_reset',
        'auditable_type' => User::class,
        'auditable_id' => $owner->id,
    ]);
});

test('a platform user without the password reset permission is refused', function () {
    $role = Role::firstOrCreate(['name' => 'Farm Viewer', 'guard_name' => 'api']);
    $role->syncPermissions(['platform.access']);
    $user = User::factory()->create();
    $user->assignRole($role);
    $tenant = Tenant::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.$user->createToken('test')->plainTextToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/owner/reset-password')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

test('farm owner can still create their own farm', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->ownerToken)
        ->postJson('/api/v1/tenants', [
            'name' => 'Owner Created Farm',
            'slug' => 'owner-created-farm',
        ]);

    $response->assertStatus(201)->assertJsonPath('data.name', 'Owner Created Farm');

    $tenant = Tenant::where('slug', 'owner-created-farm')->firstOrFail();
    expect((int) $tenant->owner_id)->toBe($owner->id);
    expect(DB::table('tenant_user')
        ->where('tenant_id', $tenant->id)
        ->where('user_id', $owner->id)
        ->exists())->toBeTrue();
});

test('platform admin can update a farm name and status', function () {
    $tenant = Tenant::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->patchJson('/api/v1/seller/farms/'.$tenant->id, [
            'name' => 'Renamed Farm',
            'status' => 'suspended',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Renamed Farm')
        ->assertJsonPath('data.status', 'suspended');

    $tenant->refresh();
    expect($tenant->name)->toBe('Renamed Farm');
    expect($tenant->status)->toBe('suspended');
});

test('platform admin cannot set an unsupported farm status', function () {
    $tenant = Tenant::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->patchJson('/api/v1/seller/farms/'.$tenant->id, ['status' => 'inactive'])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');

    expect($tenant->refresh()->status)->toBe('active');
});

test('platform admin can assign and cancel a farm subscription', function () {
    $tenant = Tenant::factory()->create();

    $subscribe = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/subscription', [
            'plan_code' => 'growth',
        ]);

    $subscribe->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.plan.code', 'growth');

    $subscription = Subscription::where('tenant_id', $tenant->id)->firstOrFail();
    expect($subscription->plan->code)->toBe('growth');

    $cancel = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/subscription/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'active');

    expect($cancel->json('data.canceled_at'))->not->toBeNull();
    expect($subscription->refresh()->status)->toBe('active');
    expect($subscription->canceled_at)->not->toBeNull();
});

test('platform admin cannot assign an inactive plan with 422', function () {
    $tenant = Tenant::factory()->create();
    $plan = Plan::factory()->create(['is_active' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/subscription', [
            'plan_code' => $plan->code,
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test('platform admin deletes a farm and revokes its access', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create();
    $owner->forceFill(['current_tenant_id' => $tenant->id])->save();
    $tenant->users()->attach($owner->id, ['role' => 'Owner', 'is_owner' => true]);
    Subscription::factory()->for($tenant)->create();

    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();
    $impersonation = Impersonation::create([
        'impersonator_id' => $admin->id,
        'user_id' => $owner->id,
        'tenant_id' => $tenant->id,
        'reason' => 'Delete farm test',
        'expires_at' => now()->addMinutes(15),
    ]);

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson('/api/v1/seller/farms/'.$tenant->id)
        ->assertOk()
        ->assertJsonPath('data.id', $tenant->id)
        ->assertJsonPath('data.deleted', true);

    expect(Tenant::find($tenant->id))->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id)?->trashed())->toBeTrue();
    expect(DB::table('tenant_user')->where('tenant_id', $tenant->id)->exists())->toBeFalse();
    expect(Subscription::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
    expect($owner->refresh()->current_tenant_id)->toBeNull();
    expect($impersonation->refresh()->ended_at)->not->toBeNull();
    expect(Tenant::withTrashed()->find($tenant->id)?->slug)->not->toBe($tenant->slug);

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Reused Slug Farm',
            'slug' => $tenant->slug,
            'owner_email' => 'reused-slug-owner@example.com',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.slug', $tenant->slug);
});

test('farm owner receives 403 for seller farm routes', function () {
    $tenant = Tenant::factory()->create();

    $requests = [
        ['GET', '/api/v1/seller/farms', []],
        ['POST', '/api/v1/seller/farms', ['name' => 'Forbidden', 'owner_email' => 'forbidden@example.com']],
        ['PATCH', '/api/v1/seller/farms/'.$tenant->id, ['name' => 'Forbidden']],
        ['DELETE', '/api/v1/seller/farms/'.$tenant->id, []],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/subscription', ['plan_code' => 'growth']],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/subscription/cancel', []],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/owner/reset-password', []],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/impersonate', ['reason' => 'Forbidden attempt']],
    ];

    foreach ($requests as [$method, $uri, $body]) {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken)
            ->json($method, $uri, $body)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
});
