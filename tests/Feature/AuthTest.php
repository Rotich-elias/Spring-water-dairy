<?php

use App\Models\User;

beforeEach(function () {
    $this->owner = User::where('email', 'owner@springwaterdairy.local')->first();
});

test('login succeeds with email and returns token and user', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'owner@springwaterdairy.local',
        'password' => 'change-me-immediately',
        'device_name' => 'test-device',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['token', 'user' => ['id', 'name', 'email', 'role', 'permissions']],
    ]);
    expect($response->json('data.user.role'))->toBe('Owner');
});

test('login succeeds with phone number', function () {
    $this->owner->update(['phone' => '0712345678']);

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => '0712345678',
        'password' => 'change-me-immediately',
    ]);

    $response->assertStatus(200);
});

test('login fails with invalid credentials', function () {
    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'owner@springwaterdairy.local',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(401);
    $response->assertJson([
        'error' => ['code' => 'unauthenticated'],
    ]);
});

test('login requires login and password', function () {
    $response = $this->postJson('/api/v1/auth/login', []);

    $response->assertStatus(422);
    $response->assertJson([
        'error' => [
            'code' => 'validation_failed',
            'fields' => [
                'login' => [
                    'Please enter your email or phone number.',
                ],
                'password' => [
                    'Please enter your password.',
                ],
            ],
        ],
    ]);
});

test('login is rate limited after repeated attempts', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'owner@springwaterdairy.local',
            'password' => 'wrong',
        ]);
    }

    $response = $this->postJson('/api/v1/auth/login', [
        'login' => 'owner@springwaterdairy.local',
        'password' => 'wrong',
    ]);

    $response->assertStatus(429);
    $response->assertJson(['error' => ['code' => 'rate_limited']]);
});

test('auth/me returns the current user with permissions', function () {
    $token = $this->owner->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['id', 'name', 'email', 'role', 'permissions'],
    ]);
    expect($response->json('data.permissions'))->toContain('milk.create');
});

test('auth/me returns 401 without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertStatus(401);
});

test('auth/logout revokes the token', function () {
    $token = $this->owner->createToken('test')->plainTextToken;

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/logout');

    $response->assertStatus(200);

    // The token should be revoked (deleted from the database).
    expect($this->owner->fresh()->tokens()->count())->toBe(0);
});
