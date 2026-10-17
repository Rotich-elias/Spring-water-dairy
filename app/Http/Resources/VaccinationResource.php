<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaccinationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'animal_id' => $this->animal_id,
            'animal_ear_tag' => $this->animal?->ear_tag,
            'condition_id' => $this->condition_id,
            'condition' => $this->condition?->name,
            'vaccination_date' => $this->vaccination_date?->toISOString(),
            'vaccine_name' => $this->vaccine_name,
            'dose' => $this->dose,
            'route' => $this->route,
            'pen_id' => $this->pen_id,
            'pen' => $this->pen?->name,
            'next_due_date' => $this->next_due_date?->toISOString(),
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
