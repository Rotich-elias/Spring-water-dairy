<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenantRole = $this->tenantRole();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $tenantRole ?? $this->getRoleNames()->first(),
            'permissions' => $this->permissionsFor($tenantRole),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function tenantRole(): ?string
    {
        $role = $this->resource->tenant_role ?? null;

        return is_string($role) && $role !== '' ? $role : null;
    }

    /**
     * @return list<string>
     */
    private function permissionsFor(?string $tenantRole): array
    {
        if ($tenantRole !== null) {
            $role = Role::with('permissions')
                ->where('name', $tenantRole)
                ->where('guard_name', 'api')
                ->first();

            if ($role !== null) {
                return $role->permissions->pluck('name')->sort()->values()->all();
            }
        }

        return $this->getAllPermissions()->pluck('name')->sort()->values()->all();
    }
}
