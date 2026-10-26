<?php

use App\Domain\Alerts\Models\Task;
use App\Models\User;

function taskMilkerToken(User $milker): string
{
    return $milker->createToken('test')->plainTextToken;
}

function taskManagerToken(): string
{
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    return $manager->createToken('test')->plainTextToken;
}

test('milker cannot create a task', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');

    $this->withHeader('Authorization', 'Bearer '.taskMilkerToken($milker))
        ->postJson('/api/v1/tasks', [
            'title' => 'Milker-created task',
            'type' => 'general',
        ])
        ->assertForbidden();
});

test('milker can complete their allocated task with a note', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $task = Task::factory()->create([
        'user_id' => $milker->id,
        'title' => 'Clean the parlour',
    ]);

    $res = $this->withHeader('Authorization', 'Bearer '.taskMilkerToken($milker))
        ->patchJson('/api/v1/tasks/'.$task->id, [
            'is_completed' => true,
            'completion_note' => 'Done after morning milking',
            'title' => 'Changed by milker',
        ]);

    $res->assertOk()
        ->assertJsonPath('data.is_completed', true)
        ->assertJsonPath('data.completion_note', 'Done after morning milking')
        ->assertJsonPath('data.title', 'Clean the parlour');
});

test('milker cannot complete a task allocated to another user', function () {
    $milker = User::factory()->create();
    $milker->assignRole('Milker');
    $task = Task::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->withHeader('Authorization', 'Bearer '.taskMilkerToken($milker))
        ->patchJson('/api/v1/tasks/'.$task->id, [
            'is_completed' => true,
            'completion_note' => 'Should not be allowed',
        ])
        ->assertForbidden();
});

test('manager can assign a task and update any task', function () {
    $managerToken = taskManagerToken();
    $milker = User::factory()->create();
    $milker->assignRole('Milker');

    $created = $this->withHeader('Authorization', 'Bearer '.$managerToken)
        ->postJson('/api/v1/tasks', [
            'title' => 'Move heifers',
            'type' => 'general',
            'user_id' => $milker->id,
        ]);

    $created->assertCreated();
    expect($created->json('data.user_id'))->toBe($milker->id);

    $this->withHeader('Authorization', 'Bearer '.$managerToken)
        ->patchJson('/api/v1/tasks/'.$created->json('data.id'), [
            'title' => 'Move heifers to paddock 4',
        ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Move heifers to paddock 4');
});
