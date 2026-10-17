<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SemenStrawResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'bull_code' => $this->bull_code,
            'bull_name' => $this->bull_name,
            'breed' => $this->breed,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'expiry_date' => $this->expiry_date?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
