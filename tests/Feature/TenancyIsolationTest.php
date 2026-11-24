<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->token = $this->owner->createToken('test')->plainTextToken;
    $this->defaultTenant = app(TenantService::class)->ensureDefaultTenant();
});

test('a user cannot see an animal belonging to another tenant', function () {
    $otherTenant = app(TenantService::class)->create([
        'name' => 'Other Farm',
        'slug' => 'other-farm',
    ], $this->owner);

    // The service leaves the new farm active, so this animal belongs to it.
    $foreignAnimal = Animal::factory()->create();
    expect($foreignAnimal->tenant_id)->toBe($otherTenant->id);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('X-Tenant-ID', (string) $this->defaultTenant->id)
        ->getJson('/api/v1/animals/'.$foreignAnimal->id)
        ->assertStatus(404);
});

test('updating settings only affects the active tenant', function () {
    $otherTenant = app(TenantService::class)->create([
        'name' => 'Other Farm',
        'slug' => 'other-farm',
    ], $this->owner);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->withHeader('X-Tenant-ID', (string) $this->defaultTenant->id)
        ->patchJson('/api/v1/settings', ['settings' => ['farm_name' => 'Renamed Dairy']])
        ->assertStatus(200);

    $defaultFarmName = DB::table('settings')
        ->where('tenant_id', $this->defaultTenant->id)
        ->where('key', 'farm_name')
        ->value('value');

    $otherFarmName = DB::table('settings')
        ->where('tenant_id', $otherTenant->id)
        ->where('key', 'farm_name')
        ->value('value');

    expect($defaultFarmName)->toBe('Renamed Dairy');
    expect($otherFarmName)->toBe('Spring Water Dairy');
});

test('switching to a tenant the user is not a member of returns 404', function () {
    $stranger = User::factory()->create();
    $foreignTenant = app(TenantService::class)->create([
        'name' => 'Stranger Farm',
        'slug' => 'stranger-farm',
    ], $stranger);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/tenants/'.$foreignTenant->id.'/switch')
        ->assertStatus(404);
});
