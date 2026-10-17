<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreatmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'animal_id' => $this->animal_id,
            'animal_ear_tag' => $this->animal?->ear_tag,
            'treatment_date' => $this->treatment_date?->toISOString(),
            'condition_id' => $this->condition_id,
            'condition' => $this->condition?->name,
            'drug_name' => $this->drug_name,
            'dose' => $this->dose,
            'route' => $this->route,
            'milk_withholding_days' => $this->milk_withholding_days,
            'meat_withholding_days' => $this->meat_withholding_days,
            'milk_safe_date' => $this->milk_safe_date?->toISOString(),
            'meat_safe_date' => $this->meat_safe_date?->toISOString(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
