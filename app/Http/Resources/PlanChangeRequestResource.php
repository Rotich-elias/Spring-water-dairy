<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanChangeRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'tenant_name' => $this->tenant_name,
            'plan_code' => $this->plan_code,
            'plan_name' => $this->plan_name,
            'current_plan_code' => $this->current_plan_code,
            'current_plan_name' => $this->current_plan_name,
            'requester_name' => $this->requester_name,
            'status' => $this->status,
            'amount' => round(((int) $this->amount_minor) / 100, 2),
            'currency' => $this->currency,
            'note' => $this->note,
            'invoice_number' => $this->invoice_number,
            'invoiced_at' => $this->formatDate($this->invoiced_at),
            'payment_reference' => $this->payment_reference,
            'payment_date' => $this->formatDate($this->payment_date),
            'rejection_reason' => $this->rejection_reason,
            'rejected_at' => $this->formatDate($this->rejected_at),
            'approver_name' => $this->approver_name,
            'approved_at' => $this->formatDate($this->approved_at),
            'canceled_at' => $this->formatDate($this->canceled_at),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }

    private function formatDate(mixed $value): ?string
    {
        return $value?->toISOString();
    }
}
