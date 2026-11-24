<?php

use App\Domain\Alerts\Services\AlertService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->defaultTenant = Tenant::where('slug', 'spring-water-dairy')->first();
    $this->service = app(AlertService::class);
    $this->method = new ReflectionMethod($this->service, 'resolveRecipientsByRole');
});

/** @return Collection<int, User> */
function alertRecipients(AlertService $service, ReflectionMethod $method): Collection
{
    return $method->invoke($service, ['Owner', 'Manager']);
}

test('alert recipients exclude users who only belong to another farm', function () {
    app(TenantContext::class)->set($this->defaultTenant);

    $otherTenant = Tenant::factory()->create();
    $outsider = User::factory()->create();
    $outsider->assignRole('Owner');

    // The factory attaches new users to the default farm; remove it so this
    // user is genuinely exclusive to the other farm.
    DB::table('tenant_user')
        ->where('tenant_id', $this->defaultTenant->id)
        ->where('user_id', $outsider->id)
        ->delete();

    DB::table('tenant_user')->insert([
        'tenant_id' => $otherTenant->id,
        'user_id' => $outsider->id,
        'role' => 'Owner',
        'is_owner' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $outsider->forceFill(['current_tenant_id' => $otherTenant->id])->save();

    $recipients = alertRecipients($this->service, $this->method);

    expect($recipients->pluck('id'))->not->toContain($outsider->id);
    expect($recipients->pluck('id'))->toContain(
        User::where('email', 'owner@springwaterdairy.local')->first()->id
    );
});

test('alert recipients fail closed without an active farm', function () {
    app(TenantContext::class)->clear();

    expect(alertRecipients($this->service, $this->method))->toHaveCount(0);
});
