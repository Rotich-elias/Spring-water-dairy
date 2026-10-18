<?php

use App\Models\AuditLog;
use App\Models\User;

test('creating a user is audited automatically', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();
    $token = $owner->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/users', [
            'name' => 'Audited User',
            'email' => 'audited@example.com',
            'phone' => '0700000002',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'Milker',
        ])
        ->assertStatus(201);

    $audit = AuditLog::where('action', 'created')
        ->where('auditable_type', User::class)
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->user_id)->toBe($owner->id);
    expect($audit->new_values['name'])->toBe('Audited User');
});

test('updating a user is audited automatically', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->first();
    $token = $owner->createToken('test')->plainTextToken;
    $user = User::factory()->create(['name' => 'Original']);

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/users/'.$user->id, ['name' => 'Updated'])
        ->assertStatus(200);

    $audit = AuditLog::where('action', 'updated')
        ->where('auditable_id', $user->id)
        ->latest()
        ->first();

    expect($audit)->not->toBeNull();
    expect($audit->old_values['name'])->toBe('Original');
    expect($audit->new_values['name'])->toBe('Updated');
});

test('audit logs cannot be created directly via API', function () {
    $token = $this->authenticateAsOwner();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/audit-logs', [])
        ->assertStatus(405);
});
