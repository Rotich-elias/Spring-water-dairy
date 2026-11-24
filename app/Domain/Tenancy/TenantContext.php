<?php

namespace App\Domain\Tenancy;

use App\Domain\Tenancy\Models\Tenant;

/**
 * Holds the farm/tenant selected for the current execution lifecycle.
 *
 * Bound as a singleton so middleware, services, models and gates all observe
 * the very same instance.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }
}
