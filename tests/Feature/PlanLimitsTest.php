<?php

use App\Domain\Tenancy\Models\Plan;

test('editing a plan preserves limit keys the console does not send', function () {
    $plan = Plan::where('code', 'growth')->firstOrFail();
    $adminToken = $this->authenticateAsPlatformAdmin();

    expect($plan->limits['milk_records'])->toBe(50000);

    $this->withHeader('Authorization', 'Bearer '.$adminToken)
        ->patchJson('/api/v1/seller/plans/'.$plan->id, [
            'limits' => ['animals' => 750],
        ])
        ->assertOk()
        ->assertJsonPath('data.limits.animals', 750)
        ->assertJsonPath('data.limits.milk_records', 50000);

    $limits = $plan->fresh()->limits;

    expect($limits['animals'])->toBe(750)
        ->and($limits['milk_records'])->toBe(50000)
        ->and($limits['milk_records_per_month'])->toBe(50000)
        ->and($limits['alert_rules'])->toBe(50);
});
