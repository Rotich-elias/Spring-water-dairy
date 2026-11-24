<?php

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Subscription;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;

beforeEach(function () {
    $this->ownerToken = $this->authenticateAsOwner();
    $this->adminToken = $this->authenticateAsPlatformAdmin();
    $this->growthPlan = Plan::where('code', 'growth')->firstOrFail();
    $this->scalePlan = Plan::where('code', 'scale')->firstOrFail();

    $this->asOwner = fn () => $this->withHeader('Authorization', 'Bearer '.$this->ownerToken);
    $this->asAdmin = fn () => $this->withHeader('Authorization', 'Bearer '.$this->adminToken);

    $this->record = function (Tenant $tenant, Plan $plan, string $status, string $amount): PlanChangeRequest {
        return PlanChangeRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'plan_id' => $plan->id,
            'plan_code' => $plan->code,
            'plan_name' => $plan->name,
            'current_plan_code' => 'starter',
            'status' => $status,
            'amount_minor' => (int) $amount,
            'payment_reference' => in_array($status, ['paid', 'approved', 'rejected'], true)
                ? 'TXN-'.$tenant->id.'-'.$plan->code
                : null,
            'payment_date' => in_array($status, ['paid', 'approved', 'rejected'], true)
                ? '2026-11-10 09:30:00'
                : null,
        ]);
    };
});

test('farm owner sees only their own payments and statistics', function () {
    $this->travelTo('2026-11-25 10:00:00');

    try {
        $tenant = app(TenantService::class)->ensureDefaultTenant();
        ($this->record)($tenant, $this->growthPlan, 'approved', '790000');
        ($this->record)($tenant, $this->scalePlan, 'cancelled', '2490000');

        $otherTenant = Tenant::factory()->create(['name' => 'Other Farm']);
        ($this->record)($otherTenant, $this->scalePlan, 'approved', '2490000');

        $response = ($this->asOwner)()->getJson('/api/v1/billing/payments')->assertOk();

        expect($response->json('data'))->toHaveCount(2)
            ->and(collect($response->json('data'))->pluck('tenant_id')->unique()->all())->toBe([$tenant->id]);

        $statistics = $response->json('statistics');
        expect($statistics['currency'])->toBe('KES')
            ->and($statistics['total_collected'])->toBe(7900)
            ->and($statistics['payment_count'])->toBe(1)
            ->and($statistics['average_payment'])->toBe(7900)
            ->and($statistics['cancellations'])->toBe(1)
            ->and($statistics['farms_with_payments'])->toBe(1)
            ->and($statistics['active_paid_farms'])->toBeNull()
            ->and($statistics['monthly'])->toHaveCount(6);

        $november = collect($statistics['monthly'])->firstWhere('month', '2026-11');
        expect($november['count'])->toBe(1)
            ->and($november['amount'])->toBe(7900);

        expect($response->json('meta.total'))->toBe(2);
    } finally {
        $this->travelBack();
    }
});

test('a rejected payment stays in the ledger but is not counted as collected', function () {
    $tenant = app(TenantService::class)->ensureDefaultTenant();
    ($this->record)($tenant, $this->growthPlan, 'rejected', '790000');

    $response = ($this->asOwner)()->getJson('/api/v1/billing/payments')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.status'))->toBe('rejected')
        ->and($response->json('statistics.total_collected'))->toBe(0)
        ->and($response->json('statistics.payment_count'))->toBe(0);
});

test('farm payments require authentication and billing view permission', function () {
    $this->getJson('/api/v1/billing/payments')->assertUnauthorized();

    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    $this->withHeader('Authorization', 'Bearer '.$viewer->createToken('test')->plainTextToken)
        ->getJson('/api/v1/billing/payments')
        ->assertForbidden();
});

test('platform admin sees platform wide payments, plans and active paid farms', function () {
    $this->travelTo('2026-11-25 10:00:00');

    try {
        $first = Tenant::factory()->create(['name' => 'First Farm']);
        $second = Tenant::factory()->create(['name' => 'Second Farm']);

        ($this->record)($first, $this->growthPlan, 'approved', '790000');
        ($this->record)($first, $this->scalePlan, 'paid', '2490000');
        ($this->record)($second, $this->growthPlan, 'approved', '790000');
        ($this->record)($second, $this->scalePlan, 'pending', '2490000');

        Subscription::factory()->create([
            'tenant_id' => $first->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'active',
        ]);
        Subscription::factory()->create([
            'tenant_id' => $second->id,
            'plan_id' => $this->growthPlan->id,
            'status' => 'trialing',
        ]);

        $response = ($this->asAdmin)()->getJson('/api/v1/seller/payments')->assertOk();

        expect($response->json('meta.total'))->toBe(4);

        $statistics = $response->json('statistics');
        expect($statistics['total_collected'])->toBe(40700)
            ->and($statistics['payment_count'])->toBe(3)
            ->and($statistics['average_payment'])->toBe(13566.67)
            ->and($statistics['active_paid_farms'])->toBe(2)
            ->and($statistics['farms_with_payments'])->toBe(2)
            ->and($statistics['upgrades'])->toBe(2)
            ->and($statistics['by_plan'])->toHaveCount(2)
            ->and($statistics['by_farm'])->toHaveCount(2)
            ->and($statistics['status_breakdown'])->toHaveCount(6);

        $growth = collect($statistics['by_plan'])->firstWhere('plan_code', 'growth');
        expect($growth['count'])->toBe(2)
            ->and($growth['amount'])->toBe(15800);
    } finally {
        $this->travelBack();
    }
});

test('platform admin can filter payments by status, plan and farm', function () {
    $first = Tenant::factory()->create(['name' => 'First Farm']);
    $second = Tenant::factory()->create(['name' => 'Second Farm']);

    ($this->record)($first, $this->growthPlan, 'approved', '790000');
    ($this->record)($second, $this->scalePlan, 'paid', '2490000');

    ($this->asAdmin)()
        ->getJson('/api/v1/seller/payments?status=approved')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'approved');

    ($this->asAdmin)()
        ->getJson('/api/v1/seller/payments?plan_code=scale')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.plan_code', 'scale');

    ($this->asAdmin)()
        ->getJson('/api/v1/seller/payments?tenant_id='.$second->id)
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tenant_id', $second->id);
});

test('seller payments refuse farm owners and unauthenticated requests', function () {
    $this->getJson('/api/v1/seller/payments')->assertUnauthorized();

    ($this->asOwner)()->getJson('/api/v1/seller/payments')->assertForbidden();
});

test('payment history rejects invalid filters', function () {
    ($this->asAdmin)()
        ->getJson('/api/v1/seller/payments?status=bogus')
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonPath('error.fields.status.0', 'The selected status is invalid.');

    ($this->asAdmin)()
        ->getJson('/api/v1/seller/payments?per_page=500')
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.per_page.0', 'The per page field must not be greater than 100.');
});
