<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalfRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'animal_id' => $this->animal_id,
            'animal_ear_tag' => $this->animal?->ear_tag,
            'colostrum_litres' => $this->colostrum_litres,
            'colostrum_hours' => $this->colostrum_hours,
            'navel_dip' => $this->navel_dip,
            'disbudding' => $this->disbudding,
            'weaning_date' => $this->weaning_date?->toISOString(),
            'weaning_weight' => $this->weaning_weight,
            'birth_weight' => $this->birth_weight,
            'outcome' => $this->outcome,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
