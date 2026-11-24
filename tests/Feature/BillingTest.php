<?php

use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Billing\Services\PlanService;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;
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

test('subscribing to growth raises the plan limits', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/billing')
        ->assertStatus(200);

    $growth = Plan::where('code', 'growth')->firstOrFail();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => $growth->code]);

    $response->assertStatus(200);
    expect($response->json('data.plan.code'))->toBe('growth');
    expect($response->json('data.limits.animals'))->toBe(500);

    expect(app(PlanService::class)->limit('animals'))->toBe(500);
});

test('a user without billing permission cannot subscribe', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/subscribe', ['plan_code' => 'growth'])
        ->assertStatus(403);
});
