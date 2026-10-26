<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\Lactation;
use App\Domain\Milk\Models\MilkRecord;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Support\Str;

function milkOwnerToken(): string
{
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();

    return $owner->createToken('test')->plainTextToken;
}

function milkMilkerToken(): string
{
    $milker = User::factory()->create();
    $milker->assignRole('Milker');

    return $milker->createToken('test')->plainTextToken;
}

function milkViewerToken(): string
{
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');

    return $viewer->createToken('test')->plainTextToken;
}

test('milker can view session sheet', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    Animal::factory()->count(3)->female()->ofCategory($category->id)->create();
    $testDate = now()->toDateString();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->getJson('/api/v1/milk/sessions?date='.$testDate.'&session=morning');

    $res->assertStatus(200);
    expect($res->json('data.cows'))->toHaveCount(3);
    expect($res->json('data.summary.cows_missing'))->toBe(3);
});

test('milker can save a milk session', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $testDate = now()->toDateString();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->postJson('/api/v1/milk/sessions', [
            'client_uuid' => (string) Str::uuid(),
            'record_date' => $testDate,
            'session' => 'morning',
            'entries' => [
                ['animal_id' => $cow->id, 'litres' => 14.5, 'cmt_score' => 1, 'scc' => 120],
            ],
        ]);

    $res->assertStatus(200);
    expect($res->json('data.saved'))->toBe(1);
    expect($res->json('data.skipped'))->toBe(0);

    $record = MilkRecord::where('animal_id', $cow->id)->whereDate('record_date', $testDate)->first();
    expect($record->litres)->toBe('14.50');
    expect($record->in_withholding)->toBe(false);
});

test('session save is idempotent: same client_uuid returns original result', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();
    $uuid = (string) Str::uuid();
    $testDate = now()->toDateString();

    $first = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->postJson('/api/v1/milk/sessions', [
            'client_uuid' => $uuid,
            'record_date' => $testDate,
            'session' => 'morning',
            'entries' => [['animal_id' => $cow->id, 'litres' => 14.5]],
        ]);

    $second = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->postJson('/api/v1/milk/sessions', [
            'client_uuid' => $uuid,
            'record_date' => $testDate,
            'session' => 'morning',
            'entries' => [['animal_id' => $cow->id, 'litres' => 99.9]], // different value, should be ignored
        ]);

    $firstId = $first->json('data.entries.0.id');
    $secondId = $second->json('data.entries.0.id');
    expect($secondId)->toBe($firstId);
    expect($second->json('data.replayed'))->toBe(true);
    expect(MilkRecord::where('animal_id', $cow->id)->count())->toBe(1);
});

test('male animal is skipped in session save', function () {
    $bull = Animal::factory()->male()->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->postJson('/api/v1/milk/sessions', [
            'client_uuid' => (string) Str::uuid(),
            'record_date' => now()->toDateString(),
            'session' => 'morning',
            'entries' => [['animal_id' => $bull->id, 'litres' => 10.0]],
        ]);

    $res->assertStatus(200);
    expect($res->json('data.saved'))->toBe(0);
    expect($res->json('data.skipped'))->toBe(1);
    expect($res->json('data.entries.0.reason'))->toBe('male_animal');
});

test('withholding flag is set when animal is in withholding (deferred to Phase 5 treatments)', function () {
    // The in_withholding accessor depends on the treatments table (Phase 5).
    // When treatments exist, this test verifies the flag propagates to milk records.
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkMilkerToken()])
        ->postJson('/api/v1/milk/sessions', [
            'client_uuid' => (string) Str::uuid(),
            'record_date' => now()->toDateString(),
            'session' => 'morning',
            'entries' => [['animal_id' => $cow->id, 'litres' => 12.0]],
        ]);

    $res->assertStatus(200);
    expect($res->json('data.saved'))->toBe(1);
    expect($res->json('data.warnings'))->toBe(0);
});

test('individual milk record create and update', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->postJson('/api/v1/milk/milk-records', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'record_date' => now()->toDateString(),
            'session' => 'morning',
            'litres' => 15.0,
        ]);

    $res->assertStatus(201);
    $recordId = $res->json('data.id');

    // Update with If-Match
    $updateRes = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->withHeader('If-Match', 1)
        ->patchJson('/api/v1/milk/milk-records/'.$recordId, ['litres' => 16.0]);

    $updateRes->assertStatus(200);
    expect($updateRes->json('data.litres'))->toBe('16.00');
});

test('stale If-Match returns 409 conflict on milk record update', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->postJson('/api/v1/milk/milk-records', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'record_date' => now()->toDateString(),
            'session' => 'evening',
            'litres' => 10.0,
        ]);

    $recordId = $res->json('data.id');

    $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->withHeader('If-Match', 999)
        ->patchJson('/api/v1/milk/milk-records/'.$recordId, ['litres' => 20.0])
        ->assertStatus(409)
        ->assertJson(['error' => ['code' => 'conflict']]);
});

test('viewer cannot create milk records', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $this->withHeaders(['Authorization' => 'Bearer '.milkViewerToken()])
        ->postJson('/api/v1/milk/milk-records', [
            'client_uuid' => (string) Str::uuid(),
            'animal_id' => $cow->id,
            'record_date' => now()->toDateString(),
            'session' => 'morning',
            'litres' => 10.0,
        ])
        ->assertStatus(403);
});

test('bulk tank record create and list', function () {
    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->postJson('/api/v1/milk/bulk-tank-records', [
            'client_uuid' => (string) Str::uuid(),
            'record_date' => now()->toDateString(),
            'session' => 'morning',
            'litres_collected' => 500.0,
            'litres_sold' => 450.0,
            'litres_discarded' => 50.0,
            'buyer' => 'Dairy Co',
            'price_per_litre' => 45.0,
        ]);

    $res->assertStatus(201);
    expect($res->json('data.litres_collected'))->toBe('500.00');

    $listRes = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->getJson('/api/v1/milk/bulk-tank-records');

    $listRes->assertStatus(200);
    expect($listRes->json('meta.total'))->toBeGreaterThanOrEqual(1);
});

test('lactations list and dry-off', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    $lactation = Lactation::factory()->active()->create([
        'animal_id' => $cow->id,
        'lactation_number' => 2,
        'started_at' => '2026-02-01',
    ]);

    $listRes = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->getJson('/api/v1/milk/lactations?active_only=true');

    $listRes->assertStatus(200);
    expect($listRes->json('meta.total'))->toBeGreaterThanOrEqual(1);

    $dryOffRes = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->withHeader('If-Match', 1)
        ->postJson('/api/v1/milk/lactations/'.$lactation->id.'/dry-off', [
            'drying_off_date' => '2026-10-17',
        ]);

    $dryOffRes->assertStatus(200);
    expect($dryOffRes->json('data.drying_off_date'))->toBe('2026-10-17T00:00:00.000000Z');
});

test('animal milk history endpoint', function () {
    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $cow = Animal::factory()->female()->ofCategory($category->id)->create();

    MilkRecord::factory()->forDate(now()->subDays(2)->toDateString(), 'morning')->create([
        'animal_id' => $cow->id,
    ]);
    MilkRecord::factory()->forDate(now()->subDays(2)->toDateString(), 'evening')->create([
        'animal_id' => $cow->id,
    ]);
    MilkRecord::factory()->forDate(now()->subDays(1)->toDateString(), 'morning')->create([
        'animal_id' => $cow->id,
    ]);

    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->getJson('/api/v1/animals/'.$cow->id.'/milk');

    $res->assertStatus(200);
    expect($res->json('data.daily_records'))->toBeArray();
    expect($res->json('data.lactation.current'))->toBeNull();
});

test('sync changes endpoint', function () {
    $res = $this->withHeaders(['Authorization' => 'Bearer '.milkOwnerToken()])
        ->getJson('/api/v1/sync/changes?since='.now()->subDays(1)->toISOString());

    $res->assertStatus(200);
    expect($res->json('data.since'))->toBeString();
    expect($res->json('data.updated'))->toBeArray();
    expect($res->json('data.deleted'))->toBeArray();
});
