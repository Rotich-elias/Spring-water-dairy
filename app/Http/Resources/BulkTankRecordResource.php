<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BulkTankRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_uuid' => $this->client_uuid,
            'record_date' => $this->record_date?->toISOString(),
            'session' => $this->session,
            'litres_collected' => $this->litres_collected,
            'litres_sold' => $this->litres_sold,
            'litres_discarded' => $this->litres_discarded,
            'buyer' => $this->buyer,
            'price_per_litre' => $this->price_per_litre,
            'version' => $this->version,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
