<?php

use App\Domain\Tenancy\Models\Tenant;
use App\Models\Impersonation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->adminToken = $this->authenticateAsPlatformAdmin();
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $this->tenant = Tenant::where('slug', config('plans.default_tenant.slug'))->firstOrFail();
});

test('platform admin impersonates a farm owner and receives a 30 minute token', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Inspect reported milk records',
        ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.user.email', $this->owner->email)
        ->assertJsonPath('data.user.role', 'Owner')
        ->assertJsonPath('data.tenant.id', $this->tenant->id);

    expect($response->json('data.token'))->toBeString()->not->toBeEmpty();
    expect(Carbon::parse($response->json('data.expires_at')))
        ->between(now()->addMinutes(29), now()->addMinutes(31));

    $impersonation = Impersonation::findOrFail($response->json('data.impersonation_id'));
    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();

    expect($impersonation->impersonator_id)->toBe($admin->id);
    expect($impersonation->user_id)->toBe($this->owner->id);
    expect($impersonation->tenant_id)->toBe($this->tenant->id);
    expect($impersonation->reason)->toBe('Inspect reported milk records');
    expect($impersonation->ended_at)->toBeNull();
    expect($impersonation->token_id)->not->toBeNull();
});

test('impersonation token works on a tenant scoped endpoint', function () {
    $session = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Review animal register',
        ])
        ->assertStatus(201)
        ->json('data');

    // Sanctum caches the authenticated user on the guard, so reset it before
    // exercising the token issued for the farm user.
    Auth::forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$session['token'])
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/animals')
        ->assertOk();
});

test('platform admin can impersonate another member of the farm', function () {
    $member = User::factory()->create();
    $member->assignRole('Milker');

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'user_id' => $member->id,
            'reason' => 'Check milker permissions',
        ])
        ->assertStatus(201)
        ->assertJsonPath('data.user.id', $member->id)
        ->assertJsonPath('data.user.role', 'Milker');
});

test('platform admin cannot impersonate another platform admin with 403', function () {
    $admin = User::where('email', config('site.platform_admin_email'))->firstOrFail();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'user_id' => $admin->id,
            'reason' => 'Attempt to impersonate admin',
        ])
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');

    expect(Impersonation::count())->toBe(0);
});

test('platform admin cannot impersonate a user outside the farm with 404', function () {
    $outsider = User::factory()->create(['email' => 'outsider@example.com']);
    $foreignTenant = Tenant::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$foreignTenant->id.'/impersonate', [
            'user_id' => $outsider->id,
            'reason' => 'Attempt cross farm access',
        ])
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

test('impersonation requires a reason of at least five characters with 422', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'abc',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['fields' => ['reason']]]);
});

test('ending an impersonation revokes the temporary token', function () {
    $session = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Temporary support session',
        ])
        ->assertStatus(201)
        ->json('data');

    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/impersonations/'.$session['impersonation_id'].'/end')
        ->assertOk()
        ->assertJsonPath('data', null);

    $impersonation = Impersonation::findOrFail($session['impersonation_id']);
    expect($impersonation->ended_at)->not->toBeNull();
    expect(DB::table('personal_access_tokens')->where('id', $impersonation->token_id)->exists())->toBeFalse();

    Auth::forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$session['token'])
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/animals')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('platform admin sees impersonation history with actor and tenant details', function () {
    $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Investigate support ticket',
        ])
        ->assertStatus(201);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller/impersonations');

    $response->assertOk();
    $record = $response->json('data.0');

    expect($record['reason'])->toBe('Investigate support ticket');
    expect($record['user']['email'])->toBe($this->owner->email);
    expect($record['target']['id'])->toBe($this->owner->id);
    expect($record['tenant']['id'])->toBe($this->tenant->id);
    expect($record['impersonator_id'])->toBeInt();
    expect($record['ended_at'])->toBeNull();
    expect($record['expires_at'])->toBeString();
});

test('an expired impersonation token cannot access tenant routes', function () {
    $session = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Short lived support session',
        ])
        ->assertStatus(201)
        ->json('data');

    $impersonation = Impersonation::findOrFail($session['impersonation_id']);
    DB::table('personal_access_tokens')
        ->where('id', $impersonation->token_id)
        ->update(['expires_at' => now()->subMinute()]);

    Auth::forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$session['token'])
        ->withHeader('X-Tenant-ID', (string) $this->tenant->id)
        ->getJson('/api/v1/animals')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('history closes impersonations whose recorded expiry has passed', function () {
    $session = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->postJson('/api/v1/seller/farms/'.$this->tenant->id.'/impersonate', [
            'reason' => 'Expired support session',
        ])
        ->assertStatus(201)
        ->json('data');

    $impersonation = Impersonation::findOrFail($session['impersonation_id']);
    DB::table('impersonations')
        ->where('id', $impersonation->id)
        ->update(['expires_at' => now()->subMinute()]);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->adminToken)
        ->getJson('/api/v1/seller/impersonations');

    $response->assertOk();
    expect($response->json('data.0.ended_at'))->not->toBeNull();

    $impersonation->refresh();
    expect($impersonation->ended_at)->not->toBeNull();
    expect(DB::table('personal_access_tokens')->where('id', $impersonation->token_id)->exists())->toBeFalse();
});
