<?php

use App\Domain\Alerts\Services\AlertService;
use App\Domain\Alerts\Services\AlertsService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    app(AlertsService::class)->generateFromData();
    app(AlertService::class)->evaluate();
})
    ->dailyAt('06:00')
    ->name('generate-alerts')
    ->withoutOverlapping();
