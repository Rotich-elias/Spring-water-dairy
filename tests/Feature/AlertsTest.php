<?php

use App\Domain\Alerts\Models\AlertRule;
use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Models\Lookup;
use App\Models\User;
use Carbon\Carbon;

test('owner can list alert rules', function () {
    $token = $this->authenticateAsOwner();

    AlertRule::factory()->count(3)->create();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/alert-rules');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect(count($data))->toBe(3);
});

test('generate creates alerts from existing data', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $condition = Lookup::where('type', 'health-conditions')->where('name', 'Mastitis')->first();

    Treatment::factory()->create([
        'animal_id' => $cow->id,
        'condition_id' => $condition->id,
        'drug_name' => 'Mastitis Guard',
        'dose' => '10ml',
        'route' => 'IM',
        'milk_withholding_days' => 4,
        'meat_withholding_days' => 14,
        'milk_safe_date' => Carbon::today()->addDays(4)->toDateString(),
        'meat_safe_date' => Carbon::today()->addDays(14)->toDateString(),
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/alert-rules/generate');

    $res->assertStatus(200);
    expect($res->json('data.created'))->toBeGreaterThanOrEqual(1);
});

test('owner can dismiss an alert', function () {
    $token = $this->authenticateAsOwner();

    $alertRule = AlertRule::factory()->create();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/alert-rules/'.$alertRule->id);

    $res->assertStatus(200);
    expect(AlertRule::find($alertRule->id)->is_active)->toBeFalse();
});

test('owner can update an alert rule', function () {
    $token = $this->authenticateAsOwner();

    $alertRule = AlertRule::factory()->create(['is_active' => true]);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/alert-rules/'.$alertRule->id, [
            'is_active' => false,
        ]);

    $res->assertStatus(200);
    expect(AlertRule::find($alertRule->id)->is_active)->toBeFalse();
});

test('non-owner cannot list alert rules', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/alert-rules')
        ->assertStatus(403);
});
