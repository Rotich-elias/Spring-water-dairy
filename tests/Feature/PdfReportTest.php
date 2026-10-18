<?php

use App\Models\User;

test('owner can prepare a PDF report with farm branding', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/animal-register/pdf');

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data)->toHaveKey('branding');
    expect($data)->toHaveKey('report_type');
    expect($data)->toHaveKey('generated_at');
    expect($data)->toHaveKey('data');
    expect($data['report_type'])->toBe('animal-register');
    expect($data['branding'])->toHaveKey('farm_name');
    expect($data['branding']['farm_name'])->toBe('Spring Water Dairy');
    expect($data['branding'])->toHaveKey('farm_address');
    expect($data['branding'])->toHaveKey('farm_contact_phone');
    expect($data['branding'])->toHaveKey('farm_contact_email');
    expect($data['branding'])->toHaveKey('farm_logo_path');
});

test('owner can prepare a milk production PDF report with filters', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/milk-production/pdf', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data['report_type'])->toBe('milk-production');
    expect($data)->toHaveKey('branding');
    expect($data)->toHaveKey('data');
});

test('owner can prepare a mortality PDF report', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/mortality/pdf', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
        ]);

    $res->assertStatus(200);
    $data = $res->json('data');
    expect($data['report_type'])->toBe('mortality');
    expect($data)->toHaveKey('branding');
});

test('non-reports user cannot generate PDF reports', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/animal-register/pdf')
        ->assertStatus(403);
});
