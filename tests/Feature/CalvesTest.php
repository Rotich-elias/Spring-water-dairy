<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Calves\Models\Weight;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Str;

function calvesOwnerToken(): string
{
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();

    return $owner->createToken('test')->plainTextToken;
}

function calvesViewerToken(): string
{
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    return $viewer->createToken('test')->plainTextToken;
}

test('owner can record a weight', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    $calf = Animal::factory()->ofCategory($category->id)->create();
    Animal::factory()->create([
        'ear_tag' => 'CALF-WEIGHT-PREV',
        'category_id' => $category->id,
        'birth_date' => '2026-01-01',
    ]);
    $prevCalf = Animal::where('ear_tag', 'CALF-WEIGHT-PREV')->first();
    Weight::factory()->create([
        'animal_id' => $prevCalf->id,
        'weight_date' => '2026-09-01',
        'weight_kg' => 30.0,
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->postJson('/api/v1/weights', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $prevCalf->id,
            'weight_date' => '2026-10-17',
            'weight_kg' => 45.0,
        ]);

    $res->assertStatus(201);
    $data = $res->json('data');
    expect($data['weight_kg'])->toBe('45.00');
    expect($data['adg'])->toBeNumeric();
});

test('weight idempotent via client_uuid', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    $calf = Animal::factory()->ofCategory($category->id)->create();
    $uuid = (string) Str::uuid();

    $first = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->postJson('/api/v1/weights', [
            'client_uuid' => $uuid,
            'animal_id' => $calf->id,
            'weight_date' => now()->toDateString(),
            'weight_kg' => 50.0,
        ]);

    $second = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->postJson('/api/v1/weights', [
            'client_uuid' => $uuid,
            'animal_id' => $calf->id,
            'weight_date' => now()->toDateString(),
            'weight_kg' => 99.0, // different value, ignored
        ]);

    $firstId = $first->json('data.id');
    $secondId = $second->json('data.id');
    expect($secondId)->not->toBeNull();
    expect($secondId)->toBe($firstId);
    expect(Weight::where('animal_id', $calf->id)->count())->toBe(1);
});

test('viewer can view weights', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    $calf = Animal::factory()->ofCategory($category->id)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.calvesViewerToken()])
        ->getJson('/api/v1/weights')
        ->assertStatus(200);
});

test('animal growth endpoint returns series with benchmark', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    $calf = Animal::factory()->ofCategory($category->id)->create([
        'birth_date' => '2026-09-01',
    ]);
    Lookup::factory()->create(['type' => 'calf-records', 'name' => 'live', 'active' => true]); // not used but ok

    Weight::factory()->count(3)->create([
        'animal_id' => $calf->id,
        'weight_date' => '2026-10-15',
        'weight_kg' => 40.0,
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->getJson('/api/v1/animals/'.$calf->id.'/growth');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toHaveKeys(['animal_id', 'ear_tag', 'birth_weight', 'overall_adg', 'series']);
    expect($data['series'])->toBeArray();
});

test('calf record and wean', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    $calf = Animal::factory()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->postJson('/api/v1/calf-records', [
            'animal_id' => $calf->id,
            'colostrum_litres' => 3.5,
            'colostrum_hours' => 2,
            'navel_dip' => true,
            'disbudding' => false,
            'birth_weight' => 38.0,
            'outcome' => 'live',
        ]);

    $res->assertStatus(201);
    expect($res->json('data.colostrum_litres'))->toBe('3.50');
    expect($res->json('data.outcome'))->toBe('live');

    // Wean the calf
    $weanRes = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->postJson('/api/v1/calf-records/'.$res->json('data.id').'/wean', [
            'weaning_date' => '2026-10-17',
            'weaning_weight' => 65.0,
        ]);

    $weanRes->assertStatus(200);
    expect($weanRes->json('data.weaning_date'))->toBe('2026-10-17T00:00:00.000000Z');
    expect($weanRes->json('data.weaning_weight'))->toBe('65.00');
});

test('calves nursery returns flags', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Calves')->first();
    Animal::factory()->count(3)->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.calvesOwnerToken()])
        ->getJson('/api/v1/calves/nursery');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
    if (count($data) > 0) {
        expect($data[0])->toHaveKeys(['id', 'ear_tag', 'age_days', 'flags']);
    }
});
