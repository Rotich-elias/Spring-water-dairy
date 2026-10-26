<?php

use App\Models\Lookup;
use App\Models\User;

test('owner can create update deactivate and delete a lookup', function () {
    $token = $this->authenticateAsOwner();

    $create = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [
            'type' => 'feed-groups',
            'name' => 'Lactation Pellets',
            'sort_order' => 5,
        ]);

    $create->assertCreated();
    $id = $create->json('data.id');
    expect($create->json('data.name'))->toBe('Lactation Pellets');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/lookups/'.$id, ['name' => 'Lactation Mix'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Lactation Mix');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/lookups/'.$id, ['active' => false])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $default = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/feed-groups');
    expect(collect($default->json('data'))->pluck('name'))->not->toContain('Lactation Mix');

    $inactive = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/feed-groups?include_inactive=1');
    expect(collect($inactive->json('data'))->pluck('name'))->toContain('Lactation Mix');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v1/lookups/'.$id)
        ->assertNoContent();

    expect(Lookup::withTrashed()->find($id)->trashed())->toBeTrue();
});

test('duplicate lookup names are rejected', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [
            'type' => 'breeds',
            'name' => 'Friesian / Holstein',
        ])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed');
});

test('recreating a deleted lookup restores it', function () {
    $token = $this->authenticateAsOwner();

    $create = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [
            'type' => 'feed-groups',
            'name' => 'Restored Feed',
        ]);
    $id = $create->json('data.id');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v1/lookups/'.$id)
        ->assertNoContent();

    $recreate = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [
            'type' => 'feed-groups',
            'name' => 'Restored Feed',
        ]);

    $recreate->assertCreated();
    expect($recreate->json('data.id'))->toBe($id);
    expect(Lookup::find($id))->not->toBeNull();
});

test('viewer cannot edit lookups', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', ['type' => 'pens', 'name' => 'Nope'])
        ->assertStatus(403);
});
