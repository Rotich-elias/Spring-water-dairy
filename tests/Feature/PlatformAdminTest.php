<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

test('seeded platform admin has the platform role and resource flags', function () {
    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();

    expect($admin->hasRole('Platform Admin', 'api'))->toBeTrue();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->authenticateAsPlatformAdmin())
        ->getJson('/api/v1/auth/me')
        ->assertOk();

    $response->assertJsonPath('data.is_platform_admin', true)
        ->assertJsonPath('data.is_seller', true)
        ->assertJsonPath('data.role', 'Platform Admin');
});

test('platform admin is not a member of any tenant', function () {
    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();

    expect($admin->tenants()->count())->toBe(0);
    expect($admin->current_tenant_id)->toBeNull();
    expect(DB::table('tenant_user')->where('user_id', $admin->id)->exists())->toBeFalse();
});

test('platform admin has platform permissions but no farm operational permissions', function () {
    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();

    foreach (['platform.access', 'site.view', 'site.update', 'plans.create', 'inquiries.view', 'inquiries.manage', 'farms.delete', 'farms.password_reset'] as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }

    foreach (['animals.view', 'milk.view', 'health.view', 'tenants.delete', 'billing.manage'] as $permission) {
        expect($admin->can($permission))->toBeFalse();
    }
});

test('farm owner has no platform permissions', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();

    expect($owner->hasRole('Owner', 'api'))->toBeTrue();
    expect($owner->hasRole('Platform Admin', 'api'))->toBeFalse();

    foreach ([
        'platform.access', 'site.view', 'site.update',
        'plans.create', 'plans.update', 'plans.delete',
        'subscriptions.manage', 'inquiries.view', 'inquiries.manage',
        'impersonate', 'farms.provision', 'farms.delete', 'farms.password_reset',
    ] as $permission) {
        expect($owner->can($permission))->toBeFalse();
    }
});
