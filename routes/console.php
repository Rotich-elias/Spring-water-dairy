<?php

use App\Domain\Alerts\Services\AlertService;
use App\Domain\Alerts\Services\AlertsService;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Alerts are tenant-scoped, so the scheduler must establish a farm context for
// each active tenant before querying its herd data.
Schedule::call(function () {
    $context = app(TenantContext::class);

    foreach (Tenant::query()->active()->orderBy('id')->get() as $tenant) {
        $context->set($tenant);

        try {
            app(AlertsService::class)->generateFromData();
            app(AlertService::class)->evaluate();
        } finally {
            $context->clear();
        }
    }
})
    ->dailyAt('06:00')
    ->name('generate-alerts')
    ->withoutOverlapping();
