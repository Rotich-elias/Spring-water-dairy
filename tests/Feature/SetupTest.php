<?php

test('setup status reports completion steps', function () {
    $token = $this->authenticateAsOwner();

    $response = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/setup/status');

    $response->assertStatus(200);
    $response->assertJsonStructure([
        'data' => ['completed', 'steps'],
    ]);
    $steps = $response->json('data.steps');
    expect($steps)->toBeArray();
    foreach ($steps as $step) {
        expect($step)->toHaveKeys(['name', 'done']);
    }
});
