<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MilkRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'animal_id' => $this->animal_id,
            'animal_ear_tag' => $this->animal?->ear_tag,
            'record_date' => $this->record_date?->toISOString(),
            'session' => $this->session,
            'litres' => $this->litres,
            'cmt_score' => $this->cmt_score,
            'scc' => $this->scc,
            'in_withholding' => $this->in_withholding,
            'excluded_from_sales' => $this->excluded_from_sales,
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
