<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'animal_id' => $this->animal_id,
            'animal_ear_tag' => $this->animal?->ear_tag,
            'service_date' => $this->service_date?->toISOString(),
            'bull_id' => $this->bull_id,
            'bull_code' => $this->bull?->bull_code,
            'bull_name' => $this->bull?->bull_name,
            'inseminator' => $this->inseminator,
            'service_type' => $this->service_type,
            'expected_calving_date' => $this->expected_calving_date?->toISOString(),
            'target_dry_date' => $this->target_dry_date?->toISOString(),
            'pd_window_start' => $this->pd_window_start?->toISOString(),
            'pd_window_end' => $this->pd_window_end?->toISOString(),
            'reproductive_status' => $this->reproductive_status,
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
