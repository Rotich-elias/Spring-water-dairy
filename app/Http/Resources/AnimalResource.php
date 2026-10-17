<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnimalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'ear_tag' => $this->ear_tag,
            'name' => $this->name,
            'sex' => $this->sex,
            'birth_date' => $this->birth_date?->toISOString(),
            'category' => $this->category?->name,
            'category_id' => $this->category_id,
            'pen' => $this->pen?->name,
            'pen_id' => $this->pen_id,
            'status' => $this->status?->name,
            'status_id' => $this->status_id,
            'breed' => $this->breed?->name,
            'breed_id' => $this->breed_id,
            'age' => $this->age,
            'parity' => $this->parity,
            'days_in_milk' => $this->days_in_milk,
            'in_withholding' => $this->in_withholding,
            'reproductive_status' => $this->reproductive_status,
            'exit_date' => $this->exit_date?->toISOString(),
            'exit_reason' => $this->exit_reason,
            'exit_price' => $this->exit_price,
            'dam_id' => $this->dam_id,
            'sire_id' => $this->sire_id,
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
