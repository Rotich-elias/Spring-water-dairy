<?php

use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->first();
    $this->token = $this->owner->createToken('test')->plainTextToken;
});

test('owner can list users', function () {
    User::factory()->create(['name' => 'Milker One', 'email' => 'milker@example.com']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/users');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => [['id', 'name', 'email', 'role', 'permissions']],
        'meta' => ['page', 'per_page', 'total', 'last_page'],
    ]);
    expect($response->json('meta.total'))->toBeGreaterThanOrEqual(2);
});

test('owner can create a user', function () {
    $role = Role::firstWhere('name', 'Milker');

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/users', [
            'name' => 'New Milker',
            'email' => 'newmilker@example.com',
            'phone' => '0700000001',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'Milker',
        ]);

    $response->assertStatus(201);
    $response->assertJson([
        'data' => ['name' => 'New Milker', 'email' => 'newmilker@example.com', 'role' => 'Milker'],
    ]);
    expect($response->json('data.permissions'))->toContain('milk.create');
});

test('duplicate email is rejected when creating a user', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->postJson('/api/v1/users', [
            'name' => 'Dup',
            'email' => 'owner@springwaterdairy.local',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'Milker',
        ]);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => [
            'code' => 'validation_failed',
            'fields' => [
                'email' => [
                    'This email is already registered.',
                ],
            ],
        ],
    ]);
});

test('owner can view a single user', function () {
    $user = User::factory()->create(['name' => 'Viewer One']);

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->getJson('/api/v1/users/'.$user->id);

    $response->assertStatus(200);
    expect($response->json('data.id'))->toBe($user->id);
});

test('owner can update a user', function () {
    $user = User::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->patchJson('/api/v1/users/'.$user->id, [
            'name' => 'Updated Name',
            'role' => 'Viewer',
        ]);

    $response->assertStatus(200);
    expect($response->json('data.name'))->toBe('Updated Name');
    expect($response->json('data.role'))->toBe('Viewer');
});

test('owner cannot delete their own account', function () {
    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v1/users/'.$this->owner->id);

    $response->assertStatus(403);
    $response->assertJson(['error' => ['code' => 'forbidden']]);
});

test('owner can delete another user', function () {
    $user = User::factory()->create();

    $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
        ->deleteJson('/api/v1/users/'.$user->id);

    $response->assertStatus(204);
});
