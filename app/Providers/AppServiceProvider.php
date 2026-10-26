<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
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
        // Delegate every Gate::allows() check to spatie's hasPermissionTo() so that
        // permissions stored in the database (and seeded after providers boot) are
        // enforced by controllers using $this->authorize() / Gate::allows().
        Gate::before(function (?User $user, string $ability): ?bool {
            if (! $user) {
                return null;
            }

            return $user->hasPermissionTo($ability, 'api') ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(
                (int) config('app.login_rate_limit', 5)
            )->by($request->ip().':'.$request->input('login', ''));
        });
    }
}
