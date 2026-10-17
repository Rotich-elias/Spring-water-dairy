<?php

namespace App\Providers;

use App\Models\Setting;
use App\Models\User;
use App\Domain\Audit\AuditObserver;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Automatic audit trail — every write to audited models is recorded.
        User::observe(AuditObserver::class);
        Setting::observe(AuditObserver::class);
    }
}
