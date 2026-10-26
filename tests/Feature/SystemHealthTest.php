<?php

use App\Models\User;

test('owner can read system health', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/system/health');

    $res->assertOk();
    expect($res->json('data.healthy'))->toBeTrue();
    expect($res->json('data.checks'))->toHaveKeys(['database', 'cache', 'queue', 'storage']);
    expect($res->json('data.runtime.php'))->toBe(PHP_VERSION);
});

test('users without system health permission are forbidden', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/system/health')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});
