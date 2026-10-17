<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LactationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'animal_id' => $this->animal_id,
            'lactation_number' => $this->lactation_number,
            'started_at' => $this->started_at?->toISOString(),
            'ended_at' => $this->ended_at?->toISOString(),
            'total_litres' => $this->total_litres,
            'peak_yield' => $this->peak_yield,
            'peak_day' => $this->peak_day,
            'drying_off_date' => $this->drying_off_date?->toISOString(),
            'days_in_milk' => $this->days_in_milk,
            'is_dry_off' => $this->is_dry_off,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
