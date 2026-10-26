<?php

use App\Models\User;

test('roles list includes permissions', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/roles');

    $response->assertStatus(200);
    $roles = $response->json('data');
    $roleNames = collect($roles)->pluck('name')->all();
    expect($roleNames)->toContain('Owner');
    expect($roleNames)->toContain('Milker');
    $ownerRole = collect($roles)->firstWhere('name', 'Owner');
    expect($ownerRole['permissions'])->toBeArray();
});

test('non-owner cannot list roles', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $token = $milker->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/roles')
        ->assertStatus(403);
});
