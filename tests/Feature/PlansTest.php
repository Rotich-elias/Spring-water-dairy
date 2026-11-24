<?php

use Carbon\Carbon;

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

test('plan catalog marks the subscribed plan current for signed-in users', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => 'growth'])
        ->assertOk();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/plans')
        ->assertOk();

    $current = array_values(array_filter(
        $response->json('data'),
        fn (array $plan): bool => $plan['current'] === true
    ));

    expect($current)->toHaveCount(1);
    expect($current[0]['code'])->toBe('growth');
});

test('plan catalog follows billing fallback when a cancelled period ends', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => 'growth'])
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/cancel')
        ->assertOk();

    $currentCodes = function () use ($token): array {
        $plans = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/plans')
            ->assertOk()
            ->json('data');

        return array_values(array_map(
            fn (array $plan): string => $plan['code'],
            array_filter($plans, fn (array $plan): bool => $plan['current'] === true)
        ));
    };

    expect($currentCodes())->toBe(['growth']);

    $periodEnd = Carbon::parse(
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/billing')
            ->json('data.subscription.current_period_end')
    );

    $this->travelTo($periodEnd->copy()->addDay());

    try {
        expect($currentCodes())->toBe(['starter']);
    } finally {
        $this->travelBack();
    }
});

test('plans are ordered by sort order', function () {
    $response = $this->getJson('/api/v1/plans');

    $response->assertOk();

    expect(array_column($response->json('data'), 'code'))->toBe(['starter', 'growth', 'scale']);
});
