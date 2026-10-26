<?php

use App\Domain\Animals\Models\Animal;
use App\Domain\Milk\Models\SyncState;
use App\Models\Lookup;

test('sync changes include friendly entity types and full records', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $animal = Animal::factory()->female()->ofCategory($category->id)->create([
        'ear_tag' => 'SYNC-001',
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/sync/changes?since='.now()->subMinutes(5)->toISOString());

    $res->assertOk();
    $updated = collect($res->json('data.updated'));
    $row = $updated->firstWhere('type', 'animals');

    expect($row)->not->toBeNull();
    expect($row['id'])->toBe($animal->id);
    expect($row['data'])->toHaveKey('ear_tag');
    expect($row['data']['ear_tag'])->toBe('SYNC-001');
});

test('deleted records are reported with friendly types', function () {
    $token = $this->authenticateAsOwner();

    $category = Lookup::where('type', 'categories')->where('name', 'Milking Cows')->first();
    $animal = Animal::factory()->female()->ofCategory($category->id)->create();
    $id = $animal->id;

    $animal->delete();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/sync/changes?since='.now()->subMinutes(5)->toISOString());

    $res->assertOk();
    expect(collect($res->json('data.deleted')))
        ->toContain(['type' => 'animals', 'id' => $id]);
});

test('sync requires animals view permission', function () {
    $this->getJson('/api/v1/sync/changes')->assertStatus(401);
    expect(SyncState::query()->count())->toBe(0);
});
