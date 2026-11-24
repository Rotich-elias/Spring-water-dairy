<?php

namespace App\Providers;

use App\Domain\Billing\Contracts\PaymentGateway;
use App\Domain\Billing\Gateways\PaystackGateway;
use App\Domain\Tenancy\TenantContext;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->bind(PaymentGateway::class, PaystackGateway::class);
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

            $tenant = app(TenantContext::class)->tenant();

            if (! $tenant) {
                return $user->hasPermissionTo($ability, 'api') ? true : null;
            }

            $membership = $user->tenants()
                ->whereKey($tenant->getKey())
                ->first();

            if (! $membership) {
                return false;
            }

            $pivotRole = $membership->pivot->role;

            if (! filled($pivotRole)) {
                return $user->hasPermissionTo($ability, 'api') ? true : null;
            }

            // Membership roles take precedence inside a tenant. Legacy users
            // assigned a Spatie role without a pivot role keep the old behavior.
            $role = Role::query()
                ->where('name', $pivotRole)
                ->where('guard_name', 'api')
                ->first();

            return $role?->hasPermissionTo($ability, 'api') ? true : null;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(
                (int) config('app.login_rate_limit', 5)
            )->by($request->ip().':'.$request->input('login', ''));
        });
    }
}
