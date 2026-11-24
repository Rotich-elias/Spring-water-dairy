<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    /**
     * Resolve the active farm from the request header, the user's last selection
     * or their first membership, then scope the rest of the request to it.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $identifier = $request->header('X-Tenant-ID');

        if (filled($identifier)) {
            $tenant = $this->tenantFromIdentifier((string) $identifier);

            if (! $tenant || ! $user->tenants()->whereKey($tenant->id)->exists()) {
                abort(404, 'No active farm is available for this account.');
            }
        } else {
            /** @var Tenant|null $tenant */
            $tenant = null;

            if ($user->current_tenant_id) {
                $tenant = Tenant::query()->active()->find($user->current_tenant_id);
            }

            $tenant ??= $user->tenants()->active()->orderBy('tenant_user.created_at')->first();
        }

        if (! $tenant || ! $user->tenants()->whereKey($tenant->id)->exists()) {
            abort(404, 'No active farm is available for this account.');
        }

        app(TenantContext::class)->set($tenant);
        Context::add('tenant_id', $tenant->id);

        return $next($request);
    }

    private function tenantFromIdentifier(string $identifier): ?Tenant
    {
        return Tenant::query()
            ->active()
            ->when(
                is_numeric($identifier),
                fn ($query) => $query->whereKey((int) $identifier),
                fn ($query) => $query->where('slug', $identifier)
            )
            ->first();
    }
}
