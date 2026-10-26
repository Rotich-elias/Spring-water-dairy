<?php

use App\Models\User;

test('unauthenticated api requests return the error envelope', function () {
    $this->getJson('/api/v1/animals')
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'unauthenticated');
});

test('validation failures return the error envelope with fields', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/lookups', [])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'validation_failed')
        ->assertJsonStructure(['error' => ['message', 'fields']]);
});

test('forbidden actions return the error envelope', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports')
        ->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

test('unknown api routes return a not found envelope', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/does-not-exist')
        ->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

test('unsupported methods return a method not allowed envelope', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->deleteJson('/api/v1/reports')
        ->assertStatus(405)
        ->assertJsonPath('error.code', 'method_not_allowed');
});
