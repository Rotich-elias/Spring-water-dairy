<?php

namespace App\Http\Requests;

use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('billing.manage');
    }

    /**
     * Farm-facing subscription changes are limited to the free Starter plan.
     * Paid plans are activated only after a Platform Admin approves a request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'plan_code' => [
                'required',
                'string',
                Rule::exists('plans', 'code')->where(
                    fn (Builder $query): Builder => $query
                        ->where('is_active', true)
                        ->where('price_monthly', 0)
                ),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_code.exists' => 'The selected plan is invalid.',
        ];
    }
}
