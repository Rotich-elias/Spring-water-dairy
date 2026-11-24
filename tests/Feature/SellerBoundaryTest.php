<?php

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Models\ContactInquiry;
use App\Models\Impersonation;

beforeEach(function () {
    $this->adminToken = $this->authenticateAsPlatformAdmin();
    $this->ownerToken = $this->authenticateAsOwner();
});

test('platform summary reports cross farm counts', function () {
    ContactInquiry::create([
        'name' => 'Interested Farm',
        'email' => 'interested@example.com',
        'message' => 'I would like a demo of the platform.',
    ]);
    ContactInquiry::create([
        'name' => 'Closed Inquiry',
        'email' => 'closed@example.com',
        'message' => 'Thank you, this is resolved.',
        'status' => 'closed',
    ]);
    Subscription::factory()->create(['status' => 'active']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller');

    $response->assertOk();
    expect($response->json('data'))->toBe([
        'farms' => Tenant::count(),
        'active_subscriptions' => Subscription::where('status', 'active')->count(),
        'plans' => Plan::count(),
        'new_inquiries' => ContactInquiry::where('status', 'new')->count(),
        'impersonations' => Impersonation::count(),
    ]);
});

test('unauthenticated seller requests receive 401', function () {
    $this->getJson('/api/v1/seller')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('farm owner receives 403 for platform level seller pages', function () {
    foreach ([
        '/api/v1/seller',
        '/api/v1/seller/plans',
        '/api/v1/seller/farms',
        '/api/v1/seller/impersonations',
    ] as $uri) {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken)
            ->getJson($uri)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }
});

test('platform admin without impersonation cannot reach tenant scoped routes', function () {
    $tenant = Tenant::where('slug', config('plans.default_tenant.slug'))->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/animals')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->withHeader('X-Tenant-ID', (string) $tenant->id)
        ->getJson('/api/v1/animals')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});
