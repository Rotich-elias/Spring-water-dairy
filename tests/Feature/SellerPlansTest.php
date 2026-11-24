<?php

use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;

beforeEach(function () {
    $this->adminToken = $this->authenticateAsPlatformAdmin();
    $this->ownerToken = $this->authenticateAsOwner();
});

test('platform admin can list and view plans', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller/plans');

    $response->assertOk();
    expect(array_column($response->json('data'), 'code'))->toContain('starter', 'growth', 'scale');

    $plan = Plan::where('code', 'growth')->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller/plans/'.$plan->id)
        ->assertOk()
        ->assertJsonPath('data.code', 'growth');
});

test('platform admin creates a plan with numeric price and limits', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/plans', [
            'code' => 'enterprise',
            'name' => 'Enterprise',
            'description' => 'For large herds.',
            'price_monthly' => 199.5,
            'limits' => [
                'users' => 100,
                'animals' => 5000,
                'milk_records' => null,
                'milk_records_per_month' => 100000,
                'alert_rules' => 250,
            ],
            'is_active' => true,
            'sort_order' => 4,
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.code', 'enterprise')
        ->assertJsonPath('data.price_monthly', 199.5)
        ->assertJsonPath('data.limits.animals', 5000);

    expect(Plan::where('code', 'enterprise')->firstOrFail()->sort_order)->toBe(4);
});

test('platform admin updates a plan while keeping its code unique', function () {
    $plan = Plan::where('code', 'growth')->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->patchJson('/api/v1/seller/plans/'.$plan->id, [
            'code' => 'growth',
            'name' => 'Growth Plus',
            'price_monthly' => 8900,
        ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Growth Plus')
        ->assertJsonPath('data.price_monthly', 8900);

    $plan->refresh();
    expect($plan->name)->toBe('Growth Plus');
    expect($plan->price_monthly)->toBe('8900.00');
});

test('platform admin deactivates a plan that has subscribers', function () {
    $plan = Plan::factory()->create(['code' => 'legacy-plan']);
    Subscription::factory()->create(['plan_id' => $plan->id]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson('/api/v1/seller/plans/'.$plan->id);

    $response->assertOk()
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('deactivated', true);

    $plan->refresh();
    expect($plan->is_active)->toBeFalse();
    expect(Plan::where('code', 'legacy-plan')->exists())->toBeTrue();
});

test('platform admin deletes an unused plan', function () {
    $plan = Plan::factory()->create(['code' => 'unused-plan']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->deleteJson('/api/v1/seller/plans/'.$plan->id);

    $response->assertOk()->assertJsonPath('deactivated', false);

    expect(Plan::where('code', 'unused-plan')->exists())->toBeFalse();
});

test('plan creation rejects a duplicate code with 422', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/plans', [
            'code' => 'starter',
            'name' => 'Duplicate',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['code']]]);
});

test('plan creation rejects invalid price and limits with 422', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/plans', [
            'code' => 'bad-plan',
            'name' => 'Bad Plan',
            'price_monthly' => -1,
            'limits' => ['animals' => 'many'],
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['price_monthly', 'limits.animals']]]);
});

test('farm owner receives 403 for plan create, update and delete', function () {
    $plan = Plan::where('code', 'starter')->firstOrFail();

    $requests = [
        ['POST', '/api/v1/seller/plans', ['code' => 'forbidden-plan', 'name' => 'Forbidden']],
        ['PATCH', '/api/v1/seller/plans/'.$plan->id, ['name' => 'Forbidden']],
        ['DELETE', '/api/v1/seller/plans/'.$plan->id, []],
    ];

    foreach ($requests as [$method, $uri, $body]) {
        $this->withHeader('Authorization', 'Bearer '.$this->ownerToken)
            ->json($method, $uri, $body)
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'forbidden');
    }

    expect(Plan::where('code', 'forbidden-plan')->exists())->toBeFalse();
});
