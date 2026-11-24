<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'status' => $this->status,
            'amount' => round(((int) $this->amount_minor) / 100, 2),
            'currency' => $this->currency,
            'paid_at' => $this->formatDate($this->paid_at),
            'receipt_url' => $this->receipt_url,
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value?->toISOString();
    }
}
