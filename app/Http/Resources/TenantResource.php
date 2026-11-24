<?php

namespace App\Http\Resources;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $membership = $this->membership($request->user()?->id);
        $currentTenantId = app(TenantContext::class)->id() ?? $request->user()?->current_tenant_id;
        $isCurrent = $this->current !== null
            ? (bool) $this->current
            : $currentTenantId !== null && (int) $currentTenantId === (int) $this->id;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'owner_id' => $this->owner_id,
            'role' => $membership?->role,
            'is_owner' => (bool) ($membership?->is_owner ?? ($this->owner_id === $request->user()?->id)),
            'current' => $isCurrent,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    private function membership(?int $userId): ?object
    {
        if ($userId === null) {
            return null;
        }

        $pivot = $this->resource->pivot ?? null;

        if ($pivot !== null) {
            return $pivot;
        }

        return DB::table('tenant_user')
            ->where('tenant_id', $this->id)
            ->where('user_id', $userId)
            ->select(['role', 'is_owner'])
            ->first();
    }
}
