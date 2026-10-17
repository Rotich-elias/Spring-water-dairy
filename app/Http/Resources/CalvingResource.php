<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalvingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'dam_id' => $this->dam_id,
            'dam_ear_tag' => $this->dam?->ear_tag,
            'calving_date' => $this->calving_date?->toISOString(),
            'ease_score_id' => $this->ease_score_id,
            'ease_score' => $this->easeScore?->name,
            'retained_placenta' => $this->retained_placenta,
            'notes' => $this->notes,
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
