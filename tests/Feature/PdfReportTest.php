<?php

use App\Domain\Reports\Services\PdfReportService;
use App\Models\User;

test('owner can download an animal register PDF', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/v1/reports/animal-register/pdf');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->headers->get('content-disposition'))->toContain('attachment');
    expect($res->getContent())->toStartWith('%PDF-');
});

test('owner can download a filtered milk production PDF', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/v1/reports/milk-production/pdf?from=2026-10-01&to=2026-10-18');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->getContent())->toStartWith('%PDF-');
});

test('owner can download a mortality PDF', function () {
    $token = $this->authenticateAsOwner();

    $res = $this->withHeader('Authorization', 'Bearer '.$token)
        ->get('/api/v1/reports/mortality/pdf?from=2026-10-01&to=2026-10-18');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('application/pdf');
    expect($res->getContent())->toStartWith('%PDF-');
});

test('pdf report data includes farm branding', function () {
    $report = app(PdfReportService::class)->prepare('animal-register');

    expect($report)->toHaveKey('branding');
    expect($report)->toHaveKey('report_type');
    expect($report)->toHaveKey('generated_at');
    expect($report)->toHaveKey('data');
    expect($report['report_type'])->toBe('animal-register');
    expect($report['branding'])->toHaveKey('farm_name');
    expect($report['branding']['farm_name'])->toBe('Spring Water Dairy');
    expect($report['branding'])->toHaveKey('farm_address');
    expect($report['branding'])->toHaveKey('farm_contact_phone');
    expect($report['branding'])->toHaveKey('farm_contact_email');
    expect($report['branding'])->toHaveKey('farm_logo_path');
});

test('non-reports user cannot generate PDF reports', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Viewer');
    $token = $viewer->createToken('test')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/reports/animal-register/pdf')
        ->assertStatus(403);
});
