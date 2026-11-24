<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'price_monthly' => (float) $this->price_monthly,
            'provider_price_id' => $this->provider_price_id,
            'limits' => $this->limits ?? [],
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
            'current' => (bool) $this->current,
        ];
    }
}
