<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlanRequestRejectRequest extends FormRequest
{
    /**
     * Authorization is performed by the seller controller outside tenant
     * middleware so platform permissions are evaluated without a farm scope.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
