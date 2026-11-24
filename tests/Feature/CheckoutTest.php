<?php

use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->headers = ['Authorization' => 'Bearer '.$this->owner->createToken('test')->plainTextToken];
});

test('hosted checkout and payment webhook endpoints are removed', function () {
    app(TenantService::class)->ensureDefaultTenant();

    $this->withHeaders($this->headers)
        ->postJson('/api/v1/billing/checkout', ['plan_code' => 'growth'])
        ->assertNotFound();

    $this->postJson('/api/v1/webhooks/paystack', [])
        ->assertNotFound();
});

test('billing responses omit hosted checkout and provider fields', function () {
    app(TenantService::class)->ensureDefaultTenant();

    $summary = $this->withHeaders($this->headers)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect(array_key_exists('pending_checkout', $summary))->toBeFalse()
        ->and(array_key_exists('last_payment', $summary))->toBeFalse()
        ->and(array_key_exists('provider_price_id', $summary['plan']))->toBeFalse()
        ->and(array_key_exists('provider', $summary['subscription']))->toBeFalse()
        ->and(array_key_exists('provider_subscription_id', $summary['subscription']))->toBeFalse();
});
