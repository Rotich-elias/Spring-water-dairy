<?php

use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Billing\Services\PlanService;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->token = $this->owner->createToken('test')->plainTextToken;
    $this->tenant = app(TenantService::class)->ensureDefaultTenant();
});

test('starter plan limit rejects creating beyond its animal allowance', function () {
    $starter = Plan::where('code', 'starter')->firstOrFail();
    $originalLimits = $starter->limits;

    $starter->limits = ['animals' => 1, 'users' => 3, 'milk_records_per_month' => 3000, 'alert_rules' => 5];
    $starter->save();

    try {
        $plans = app(PlanService::class);
        expect($plans->usage('animals'))->toBe(0);
        $plans->assertCan('animals');

        $categoryId = DB::table('lookups')->where('type', 'categories')->value('id');
        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/animals', [
                'ear_tag' => 'LIM-001',
                'sex' => 'F',
                'category_id' => $categoryId,
            ])
            ->assertStatus(201);

        expect($plans->usage('animals'))->toBe(1);
        expect(fn () => $plans->assertCan('animals'))->toThrow(PlanLimitExceededException::class);

        $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/animals', [
                'ear_tag' => 'LIM-002',
                'sex' => 'F',
                'category_id' => $categoryId,
            ])
            ->assertStatus(402)
            ->assertJsonPath('error.code', 'plan_limit_exceeded');
    } finally {
        $starter->limits = $originalLimits;
        $starter->save();
    }
});

test('a platform-provisioned growth plan raises the plan limits', function () {
    $growth = Plan::where('code', 'growth')->firstOrFail();
    app(PlanService::class)->subscribeForTenant($this->tenant, $growth->code);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertStatus(200);

    expect($response->json('data.plan.code'))->toBe('growth');
    expect($response->json('data.limits.animals'))->toBe(500);

    expect(app(PlanService::class)->limit('animals'))->toBe(500);
});

test('a farm cannot directly activate a paid plan', function () {
    $growth = Plan::where('code', 'growth')->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => $growth->code])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.plan_code.0', 'The selected plan is invalid.');

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->assertJsonPath('data.plan.code', 'starter');
});

test('cancelling keeps the current plan until the period ends', function () {
    app(PlanService::class)->subscribeForTenant($this->tenant, 'growth');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/cancel')
        ->assertOk();

    expect($response->json('data.subscription.status'))->toBe('active');
    expect($response->json('data.subscription.canceled_at'))->not->toBeNull();
    expect($response->json('data.subscription.ends_at'))->toBeNull();
    expect($response->json('data.plan.code'))->toBe('growth');
    expect($response->json('data.limits.animals'))->toBe(500);

    $periodEnd = Carbon::parse($response->json('data.subscription.current_period_end'));
    $this->travelTo($periodEnd->copy()->addDay());

    try {
        $after = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->getJson('/api/v1/billing')
            ->assertOk();

        expect($after->json('data.plan.code'))->toBe('starter');
        expect($after->json('data.subscription.status'))->toBe('cancelled');
        expect($after->json('data.subscription.ends_at'))->not->toBeNull();
    } finally {
        $this->travelBack();
    }
});

test('subscribing clears a scheduled cancellation', function () {
    app(PlanService::class)->subscribeForTenant($this->tenant, 'growth');

    $cancelled = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/cancel')
        ->assertOk();

    expect($cancelled->json('data.subscription.canceled_at'))->not->toBeNull();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => 'starter'])
        ->assertOk();

    expect($response->json('data.subscription.canceled_at'))->toBeNull();
    expect($response->json('data.subscription.status'))->toBe('active');
    expect($response->json('data.subscription.ends_at'))->toBeNull();
    expect($response->json('data.plan.code'))->toBe('starter');
});

test('billing usage mirrors the current summary', function () {
    $summary = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    $usage = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing/usage')
        ->assertOk()
        ->json('data');

    expect($usage['usage'])->toBe($summary['usage']);
    expect($usage['can_create'])->toBe($summary['can_create']);
    expect($usage['plan']['code'])->toBe($summary['plan']['code']);
});

test('subscribing to a deactivated plan returns 422', function () {
    $plan = Plan::factory()->create(['code' => 'retired-plan', 'is_active' => false]);

    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => $plan->code])
        ->assertStatus(422)
        ->assertJsonPath('error.fields.plan_code.0', 'The selected plan is invalid.');
});

test('a user without billing permission cannot subscribe', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => 'starter'])
        ->assertStatus(403);
});
