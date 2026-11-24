<?php

use App\Domain\Billing\Models\PlanChangeRequest;
use App\Domain\Billing\Services\PlanRequestService;
use App\Domain\Tenancy\Models\Plan;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Services\TenantService;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->farmHeaders = ['Authorization' => 'Bearer '.$this->owner->createToken('test')->plainTextToken];
    $this->adminHeaders = ['Authorization' => 'Bearer '.$this->authenticateAsPlatformAdmin()];
    $this->asFarm = function (array $headers = []) {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(array_merge($this->farmHeaders, $headers));
    };
    $this->asAdmin = function (array $headers = []) {
        $this->app['auth']->forgetGuards();

        return $this->withHeaders(array_merge($this->adminHeaders, $headers));
    };
    $this->ensureTenant = fn (): Tenant => app(TenantService::class)->ensureDefaultTenant();
    $this->createRequest = function (string $planCode = 'growth', ?string $note = null): PlanChangeRequest {
        $tenant = ($this->ensureTenant)();

        return app(PlanRequestService::class)->create(
            $tenant->id,
            $this->owner->id,
            $planCode,
            $note,
        );
    };
});

test('farm owner creates a pending request without changing the active plan', function () {
    ($this->ensureTenant)();

    $response = ($this->asFarm)()
        ->postJson('/api/v1/billing/plan-requests', [
            'plan_code' => 'growth',
            'note' => 'Herd has grown.',
        ])
        ->assertCreated();

    $requestId = $response->json('data.id');

    expect($response->json('data.status'))->toBe('pending')
        ->and($response->json('data.plan_code'))->toBe('growth')
        ->and($response->json('data.amount'))->toBe(7900)
        ->and($response->json('data.currency'))->toBe('KES');

    $summary = ($this->asFarm)()
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('starter')
        ->and($summary['subscribed_plan']['code'])->toBe('starter')
        ->and($summary['plan_request']['id'])->toBe($requestId);

    $this->assertDatabaseHas('plan_change_requests', [
        'id' => $requestId,
        'status' => 'pending',
        'amount_minor' => 790000,
        'currency' => 'KES',
        'note' => 'Herd has grown.',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plan_request.requested',
        'auditable_type' => PlanChangeRequest::class,
        'auditable_id' => $requestId,
    ]);
});

test('plan request requires authentication and billing management permission', function () {
    $this->postJson('/api/v1/billing/plan-requests', ['plan_code' => 'growth'])
        ->assertUnauthorized();

    ($this->ensureTenant)();
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/billing/plan-requests', ['plan_code' => 'growth'])
        ->assertForbidden();
});

test('only one open request can exist for a farm', function () {
    ($this->createRequest)();

    ($this->asFarm)()
        ->postJson('/api/v1/billing/plan-requests', ['plan_code' => 'scale'])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.plan_code.0', 'An upgrade request is already open for this farm.');

    expect(PlanChangeRequest::withoutGlobalScopes()->count())->toBe(1);
});

test('free and inactive plans cannot be requested', function () {
    ($this->ensureTenant)();

    ($this->asFarm)()
        ->postJson('/api/v1/billing/plan-requests', ['plan_code' => 'starter'])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.plan_code.0', 'This plan is free and does not require approval.');

    $retired = Plan::factory()->create(['code' => 'retired-plan', 'is_active' => false]);

    ($this->asFarm)()
        ->postJson('/api/v1/billing/plan-requests', ['plan_code' => $retired->code])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.plan_code.0', 'The selected plan is invalid.');
});

test('platform admin issues a numbered PDF invoice', function () {
    $this->travelTo('2026-11-20 10:00:00');

    try {
        $request = ($this->createRequest)();

        $issued = ($this->asAdmin)()
            ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
            ->assertOk()
            ->assertJsonPath('data.status', 'invoiced')
            ->assertJsonPath('data.invoice_number', 'INV-2026-00001');

        expect($issued->json('data.invoiced_at'))->not->toBeNull();

        $response = ($this->asAdmin)()
            ->get('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
            ->assertOk();

        expect($response->headers->get('content-type'))->toContain('application/pdf')
            ->and($response->headers->get('content-disposition'))->toContain('inv-2026-00001.pdf');

        ($this->asFarm)()
            ->get('/api/v1/billing/plan-requests/'.$request->id.'/invoice')
            ->assertOk();

        $this->assertDatabaseHas('plan_change_requests', [
            'id' => $request->id,
            'status' => 'invoiced',
            'invoice_number' => 'INV-2026-00001',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'plan_request.invoiced',
            'auditable_id' => $request->id,
        ]);
    } finally {
        $this->travelBack();
    }
});

test('an invoice cannot be issued twice', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
        ->assertOk();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.request.0', 'Only a pending request can be invoiced.');
});

test('platform admin records an external payment reference and date', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
        ->assertOk();

    $response = ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/paid', [
            'payment_reference' => 'TXN-ABC-123',
            'payment_date' => '2026-11-10 09:30:00',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'paid')
        ->assertJsonPath('data.payment_reference', 'TXN-ABC-123');

    expect($response->json('data.payment_date'))->toContain('2026-11-10');

    $this->assertDatabaseHas('plan_change_requests', [
        'id' => $request->id,
        'status' => 'paid',
        'payment_reference' => 'TXN-ABC-123',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plan_request.payment_recorded',
        'auditable_id' => $request->id,
    ]);
});

test('marking a request paid validates the reference and date', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/paid', [])
        ->assertUnprocessable()
        ->assertJsonStructure(['error' => ['fields' => ['payment_reference', 'payment_date']]]);
});

test('payment cannot be recorded before an invoice is issued', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/paid', [
            'payment_reference' => 'TXN-EARLY',
            'payment_date' => '2026-11-10',
        ])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.request.0', 'Only an invoiced request can be marked as paid.');
});

test('approval activates the requested plan from the recorded payment date', function () {
    $tenant = ($this->ensureTenant)();
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
        ->assertOk();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/paid', [
            'payment_reference' => 'TXN-APPROVE',
            'payment_date' => '2026-11-10 09:30:00',
        ])
        ->assertOk();

    $response = ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/approve')
        ->assertOk()
        ->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.approver_name', 'Platform Admin');

    expect($response->json('data.approved_at'))->not->toBeNull();

    $summary = ($this->asFarm)()
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('growth')
        ->and($summary['subscribed_plan']['code'])->toBe('growth')
        ->and($summary['subscription']['status'])->toBe('active')
        ->and($summary['subscription']['payment_status'])->toBe('paid')
        ->and($summary['subscription']['current_period_end'])->toContain('2026-12-10T09:30:00')
        ->and($summary['subscription']['last_payment_at'])->toContain('2026-11-10T09:30:00')
        ->and($summary['plan_request'])->toBeNull();

    $this->assertDatabaseHas('subscriptions', [
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::where('code', 'growth')->value('id'),
        'status' => 'active',
        'payment_status' => 'paid',
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plan_request.approved',
        'auditable_id' => $request->id,
    ]);
});

test('approval leaves the subscription unchanged when the requested plan is unavailable', function () {
    ($this->createRequest)();
    $request = PlanChangeRequest::withoutGlobalScopes()->firstOrFail();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/invoice')
        ->assertOk();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/paid', [
            'payment_reference' => 'TXN-RETIRED',
            'payment_date' => '2026-11-10',
        ])
        ->assertOk();

    Plan::where('code', 'growth')->firstOrFail()->forceFill(['is_active' => false])->save();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/approve')
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.request.0', 'The requested plan is no longer available.');

    expect($request->fresh()->status)->toBe('paid')
        ->and($request->fresh()->approved_at)->toBeNull();

    $summary = ($this->asFarm)()
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('starter')
        ->and($summary['subscription']['status'])->toBe('trialing');
});

test('approval before payment is rejected', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/approve')
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.request.0', 'Only a paid request can be approved.');
});

test('farm owner cancellation closes the request without changing the plan', function () {
    ($this->createRequest)();
    $request = PlanChangeRequest::withoutGlobalScopes()->firstOrFail();

    $response = ($this->asFarm)()
        ->deleteJson('/api/v1/billing/plan-requests/'.$request->id)
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    expect($response->json('data.canceled_at'))->not->toBeNull();

    $summary = ($this->asFarm)()
        ->getJson('/api/v1/billing')
        ->assertOk()
        ->json('data');

    expect($summary['plan']['code'])->toBe('starter')
        ->and($summary['plan_request'])->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plan_request.cancelled',
        'auditable_id' => $request->id,
    ]);
});

test('platform admin rejection records the reason and allows a new request', function () {
    ($this->createRequest)();
    $request = PlanChangeRequest::withoutGlobalScopes()->firstOrFail();

    $response = ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/reject', [
            'reason' => 'Payment could not be confirmed.',
        ])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected')
        ->assertJsonPath('data.rejection_reason', 'Payment could not be confirmed.');

    expect($response->json('data.rejected_at'))->not->toBeNull();

    ($this->asFarm)()
        ->postJson('/api/v1/billing/plan-requests', ['plan_code' => 'growth'])
        ->assertCreated();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'plan_request.rejected',
        'auditable_id' => $request->id,
    ]);
});

test('rejection requires a reason', function () {
    $request = ($this->createRequest)();

    ($this->asAdmin)()
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/reject', [])
        ->assertUnprocessable()
        ->assertJsonPath('error.fields.reason.0', 'The reason field is required.');
});

test('farm users cannot access platform plan request endpoints', function (string $role) {
    $user = User::factory()->create();
    $user->assignRole($role);
    $token = $user->createToken('test')->plainTextToken;
    $request = ($this->createRequest)();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/seller/plan-requests')
        ->assertForbidden();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/approve')
        ->assertForbidden();
})->with(['Owner', 'Admin', 'Manager', 'Viewer']);

test('a platform reviewer can view requests but cannot manage them', function () {
    $reviewerRole = Role::firstOrCreate(['name' => 'Plan Reviewer', 'guard_name' => 'api']);
    $reviewerRole->syncPermissions([
        Permission::findOrCreate('platform.access', 'api'),
        Permission::findOrCreate('plan_requests.view', 'api'),
    ]);
    $reviewer = User::factory()->create();
    $reviewer->assignRole($reviewerRole);
    $headers = ['Authorization' => 'Bearer '.$reviewer->createToken('test')->plainTextToken];
    $request = ($this->createRequest)();

    $this->withHeaders($headers)
        ->getJson('/api/v1/seller/plan-requests')
        ->assertOk();

    $this->withHeaders($headers)
        ->postJson('/api/v1/seller/plan-requests/'.$request->id.'/approve')
        ->assertForbidden();
});

test('unauthenticated platform requests are rejected', function () {
    $this->getJson('/api/v1/seller/plan-requests')->assertUnauthorized();
});

test('a farm cannot read or cancel another farm request', function () {
    $request = ($this->createRequest)();
    $otherFarm = Tenant::factory()->create(['status' => 'active']);
    $otherFarm->users()->attach($this->owner->id, ['role' => 'Owner', 'is_owner' => true]);

    $headers = ['X-Tenant-ID' => (string) $otherFarm->id];

    ($this->asFarm)($headers)
        ->getJson('/api/v1/billing/plan-requests/'.$request->id.'/invoice')
        ->assertNotFound();

    ($this->asFarm)($headers)
        ->deleteJson('/api/v1/billing/plan-requests/'.$request->id)
        ->assertNotFound();

    expect($request->fresh()->status)->toBe('pending');
});

test('the seller request list filters by status', function () {
    $request = ($this->createRequest)();
    $scale = Plan::where('code', 'scale')->firstOrFail();

    PlanChangeRequest::factory()->create([
        'tenant_id' => $request->tenant_id,
        'tenant_name' => $request->tenant_name,
        'plan_id' => $scale->id,
        'plan_code' => $scale->code,
        'plan_name' => $scale->name,
        'status' => PlanChangeRequest::STATUS_APPROVED,
        'amount_minor' => 2490000,
    ]);

    $all = ($this->asAdmin)()
        ->getJson('/api/v1/seller/plan-requests')
        ->assertOk()
        ->json('data');

    $pending = ($this->asAdmin)()
        ->getJson('/api/v1/seller/plan-requests?status=pending')
        ->assertOk()
        ->json('data');

    expect($all)->toHaveCount(2)
        ->and($pending)->toHaveCount(1)
        ->and($pending[0]['status'])->toBe('pending');
});

test('the invoice template escapes user supplied content', function () {
    $html = view('billing.invoice-pdf', [
        'platformName' => 'TetoDairy',
        'parentCompany' => 'Teto Technologies',
        'poweredByLabel' => 'Powered by Teto Technologies',
        'businessName' => 'TetoDairy',
        'businessAddress' => 'Nairobi, Kenya',
        'businessEmail' => 'sales@tetodairy.local',
        'businessPhone' => '+254 700 000 000',
        'invoiceNumber' => 'INV-2026-00001',
        'issuedAt' => '20 Nov 2026',
        'status' => 'Invoiced',
        'farmName' => '<script>alert(1)</script> Dairy',
        'farmAddress' => 'Nairobi',
        'farmPhone' => '',
        'farmEmail' => '',
        'requestedBy' => 'Owner',
        'planName' => 'Growth',
        'currentPlanName' => 'Starter',
        'note' => '<img src=x onerror=alert(1)>',
        'amount' => 7900.0,
        'currency' => 'KES',
        'paymentReference' => null,
        'paymentDate' => null,
        'instructions' => '<script>alert(2)</script>',
    ])->render();

    expect($html)
        ->toContain('&lt;script&gt;')
        ->not->toContain('<script>')
        ->not->toContain('<img src=x onerror=alert(1)>');
});
