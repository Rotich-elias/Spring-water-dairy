<?php

use App\Domain\Alerts\Models\Alert;
use App\Domain\Animals\Models\Animal;
use App\Domain\Health\Models\Treatment;
use App\Models\Lookup;
use App\Models\User;
use Carbon\Carbon;

test('owner can list generated alerts', function () {
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

    $generate = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/alerts/generate');
    $generate->assertOk();
    expect($generate->json('data.created'))->toBeGreaterThanOrEqual(1);

    $list = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/alerts');
    $list->assertOk();
    expect($list->json('data'))->toHaveCount(1);
    expect($list->json('data.0.type'))->toBe('withholding');
    expect($list->json('data.0.animal.ear_tag'))->toBe($cow->ear_tag);
});

test('owner can mark an alert read and dismiss it', function () {
    $token = $this->authenticateAsOwner();
    $alert = Alert::factory()->create();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/alerts/'.$alert->id.'/read')
        ->assertNoContent();
    expect($alert->fresh()->read_at)->not->toBeNull();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/alerts/'.$alert->id.'/dismiss')
        ->assertNoContent();
    expect($alert->fresh()->dismissed_at)->not->toBeNull();

    $list = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/alerts');
    expect($list->json('data'))->toHaveCount(0);
});

test('viewer cannot generate alerts', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/alerts/generate')
        ->assertStatus(403);
});
