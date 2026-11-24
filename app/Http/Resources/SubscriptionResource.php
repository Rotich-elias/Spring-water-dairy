<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status,
            'trial_ends_at' => $this->formatDate($this->trial_ends_at),
            'ends_at' => $this->formatDate($this->ends_at),
            'canceled_at' => $this->formatDate($this->canceled_at),
            'current_period_end' => $this->formatDate($this->current_period_end),
            'plan' => $this->whenLoaded('plan', fn () => new PlanResource($this->plan)),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof CarbonInterface ? $value->toISOString() : (string) $value;
    }
}
