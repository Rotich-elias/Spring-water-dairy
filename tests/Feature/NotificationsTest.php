<?php

use App\Domain\Alerts\Models\Notification;
use App\Models\User;

test('notifications are scoped to the authenticated user', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $other = User::factory()->create();

    Notification::factory()->count(2)->create(['user_id' => $owner->id, 'read_at' => null]);
    Notification::factory()->create(['user_id' => $other->id, 'read_at' => null]);

    $token = $owner->createToken('test')->plainTextToken;
    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/notifications');

    $res->assertOk();
    expect($res->json('data'))->toHaveCount(2);
    expect($res->json('meta.unread_count'))->toBe(2);
});

test('owner can mark their own notification as read', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $notification = Notification::factory()->create([
        'user_id' => $owner->id,
        'read_at' => null,
    ]);

    $token = $owner->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/notifications/'.$notification->id.'/read')
        ->assertOk();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('a user cannot mark another user notification as read', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $other = User::factory()->create();
    $notification = Notification::factory()->create([
        'user_id' => $other->id,
        'read_at' => null,
    ]);

    $token = $milker->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/notifications/'.$notification->id.'/read')
        ->assertStatus(403);

    expect($notification->fresh()->read_at)->toBeNull();
});

test('read all only marks the current user notifications', function () {
    $owner = User::where('email', 'owner@springwaterdairy.local')->firstOrFail();
    $other = User::factory()->create();

    Notification::factory()->count(2)->create(['user_id' => $owner->id, 'read_at' => null]);
    $otherNotification = Notification::factory()->create([
        'user_id' => $other->id,
        'read_at' => null,
    ]);

    $token = $owner->createToken('test')->plainTextToken;
    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/notifications/read-all')
        ->assertOk();

    expect(Notification::where('user_id', $owner->id)->whereNull('read_at')->count())->toBe(0);
    expect($otherNotification->fresh()->read_at)->toBeNull();
});
