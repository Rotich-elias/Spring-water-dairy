<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CheckoutSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'plan_code' => $this->whenLoaded('plan', fn () => $this->plan?->code),
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan?->name),
            'status' => $this->status,
            'amount' => round(((int) $this->amount_minor) / 100, 2),
            'currency' => $this->currency,
            'reference' => $this->reference,
            'authorization_url' => $this->authorization_url,
            'expires_at' => $this->formatDate($this->expires_at),
            'completed_at' => $this->formatDate($this->completed_at),
            'created_at' => $this->formatDate($this->created_at),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value?->toISOString();
    }
}
