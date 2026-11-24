<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FarmProvisionRequest extends FormRequest
{
    private ?bool $requiresPayment = null;

    /**
     * Authorization is performed in the controller, outside tenant context.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'alpha_dash', 'max:120', Rule::unique('tenants', 'slug')],
            'owner_email' => ['required', 'email', 'max:255'],
            'plan_code' => [
                'nullable',
                'string',
                Rule::exists('plans', 'code')->where('is_active', true),
            ],
            'payment_reference' => [
                Rule::when($this->requiresPayment(), ['required'], ['nullable']),
                'string',
                'max:120',
            ],
            'payment_date' => [
                Rule::when($this->requiresPayment(), ['required'], ['nullable']),
                'date',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_reference.required' => 'A payment reference is required for a paid plan.',
            'payment_date.required' => 'A payment date is required for a paid plan.',
        ];
    }

    private function requiresPayment(): bool
    {
        if ($this->requiresPayment === null) {
            $plan = Plan::active()
                ->whereRaw('LOWER(code) = ?', [mb_strtolower((string) $this->input('plan_code'))])
                ->first();

            $this->requiresPayment = $plan !== null && (float) $plan->price_monthly > 0;
        }

        return $this->requiresPayment;
    }
}
