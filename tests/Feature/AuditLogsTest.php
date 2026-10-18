<?php

use App\Models\User;

test('owner can list audit logs', function () {
    $token = $this->authenticateAsOwner();

    // Generate some audit entries by creating a user.
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/users', [
            'name' => 'Audit Test User',
            'email' => 'audit-test@example.com',
            'phone' => '0700000003',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'Milker',
        ])
        ->assertStatus(201);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/audit-logs');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    expect($res->json('meta'))->toHaveKey('total');
    expect($res->json('meta'))->toHaveKey('per_page');
});

test('audit logs can be filtered by action', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/users', [
            'name' => 'Action Filter User',
            'email' => 'action-filter@example.com',
            'phone' => '0700000004',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'Milker',
        ])
        ->assertStatus(201);

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/audit-logs?action=created');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(1);
    foreach ($data as $log) {
        expect($log['action'])->toBe('created');
    }
});

test('audit logs actions endpoint returns distinct actions', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/audit-logs/actions');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('viewer cannot list audit logs', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/audit-logs')
        ->assertStatus(403);
});
