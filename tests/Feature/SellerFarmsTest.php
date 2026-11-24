<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        'plan_code', 'plan_name', 'subscription_status', 'usage', 'created_at',
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
        ->assertJsonPath('data.slug', 'new-branch');

    $temporaryPassword = $response->json('temporary_password');
    expect($temporaryPassword)->toBeString()->not->toBeEmpty();

    $owner = User::where('email', 'new-owner@example.com')->firstOrFail();
    expect(Hash::check($temporaryPassword, $owner->password))->toBeTrue();

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

test('platform admin provisions a farm for an existing owner without revealing a password', function () {
    $owner = User::factory()->create(['email' => 'existing-owner@example.com']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms', [
            'name' => 'Existing Owner Farm',
            'owner_email' => $owner->email,
        ]);

    $response->assertStatus(201)->assertJsonMissingPath('temporary_password');

    $tenantId = $response->json('data.id');
    expect(DB::table('tenant_user')
        ->where('tenant_id', $tenantId)
        ->where('user_id', $owner->id)
        ->exists())->toBeTrue();
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

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$tenant->id.'/subscription/cancel')
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($subscription->refresh()->status)->toBe('cancelled');
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

test('farm owner receives 403 for seller farm routes', function () {
    $tenant = Tenant::factory()->create();

    $requests = [
        ['GET', '/api/v1/seller/farms', []],
        ['POST', '/api/v1/seller/farms', ['name' => 'Forbidden', 'owner_email' => 'forbidden@example.com']],
        ['PATCH', '/api/v1/seller/farms/'.$tenant->id, ['name' => 'Forbidden']],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/subscription', ['plan_code' => 'growth']],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/subscription/cancel', []],
        ['POST', '/api/v1/seller/farms/'.$tenant->id.'/impersonate', ['reason' => 'Forbidden attempt']],
    ];

    foreach ($requests as [$method, $uri, $body]) {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken)
            ->json($method, $uri, $body)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
});
