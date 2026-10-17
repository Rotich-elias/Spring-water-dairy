<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BulkTankStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('milk.create');
    }

    public function rules(): array
    {
        return [
            'client_uuid' => ['required', 'string', 'uuid'],
            'record_date' => ['required', 'date', 'before_or_equal:today'],
            'session' => ['required', 'in:morning,evening'],
            'litres_collected' => ['required', 'numeric', 'min:0'],
            'litres_sold' => ['required', 'numeric', 'min:0'],
            'litres_discarded' => ['required', 'numeric', 'min:0'],
            'buyer' => ['nullable', 'string', 'max:255'],
            'price_per_litre' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
