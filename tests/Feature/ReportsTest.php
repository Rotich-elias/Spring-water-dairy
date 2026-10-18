<?php

use App\Models\User;

test('owner can list available reports', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
    expect(count($data))->toBeGreaterThanOrEqual(1);
    $ids = collect($data)->pluck('id')->all();
    expect($ids)->toContain('animal-register');
    expect($ids)->toContain('breeding-pregnancy');
    expect($ids)->toContain('calving');
    expect($ids)->toContain('milk-production');
    expect($ids)->toContain('health-treatment');
    expect($ids)->toContain('mortality');
    foreach ($data as $report) {
        expect($report)->toHaveKey('id');
        expect($report)->toHaveKey('name');
        expect($report)->toHaveKey('description');
        expect($report)->toHaveKey('type');
    }
});

test('owner can generate animal register report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/animal-register');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('owner can generate milk production report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/milk-production', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('owner can generate mortality report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/mortality', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toBeArray();
});

test('unknown report type returns empty data', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/unknown-type');

    $res->assertStatus(200);
    expect($res->json('data'))->toBe([]);
});

test('non-reports user cannot list reports', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports')
        ->assertStatus(403);
});
