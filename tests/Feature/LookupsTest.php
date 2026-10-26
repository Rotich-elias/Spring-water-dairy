<?php

use App\Models\User;

test('lookups/all returns every lookup list', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/all');

    $response->assertStatus(200);
    $data = $response->json('data');
    expect($data)->toHaveKeys([
        'categories', 'breeds', 'pens', 'animal-statuses', 'pd-results',
        'calving-ease-scores', 'health-conditions', 'feed-groups',
    ]);
    expect($data['categories'])->toBeArray();
    expect($data['categories'][0])->toHaveKeys(['id', 'name', 'active', 'sort_order']);
});

test('milker can view lookups but not edit them', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $token = $milker->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/all')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [
            'type' => 'breeds',
            'name' => 'Milker-created breed',
        ])
        ->assertForbidden();
});

test('lookups/{type} returns one lookup list', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/breeds');

    $response->assertStatus(200);
    $breeds = $response->json('data');
    expect($breeds)->toBeArray();
    expect(collect($breeds)->pluck('name'))->toContain('Friesian / Holstein');
});

test('unknown lookup type returns 404', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/lookups/does-not-exist')
        ->assertStatus(404);
});
