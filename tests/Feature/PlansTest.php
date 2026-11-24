<?php

test('plan catalog is public', function () {
    $response = $this->getJson('/api/v1/plans');

    $response->assertOk();

    $plans = $response->json('data');
    expect($plans)->toBeArray()->not->toBeEmpty();

    foreach ($plans as $plan) {
        expect($plan)->toHaveKeys([
            'id',
            'code',
            'name',
            'description',
            'price_monthly',
            'limits',
            'is_active',
            'sort_order',
            'current',
        ]);
        expect($plan['current'])->toBeFalse();
    }
});

test('plan catalog still marks the current plan for signed-in users', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/plans');

    $response->assertOk();

    $currentPlans = array_values(array_filter(
        $response->json('data'),
        fn (array $plan): bool => $plan['current'] === true
    ));

    expect($currentPlans)->toHaveCount(1);
    expect($currentPlans[0]['code'])->toBe('starter');
});

test('plans are ordered by sort order', function () {
    $response = $this->getJson('/api/v1/plans');

    $response->assertOk();

    expect(array_column($response->json('data'), 'code'))->toBe(['starter', 'growth', 'scale']);
});
