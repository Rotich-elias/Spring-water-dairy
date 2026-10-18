<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlertRuleStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('alerts.create');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:pd_due,dry_off_due,calving_due,heat_window,no_service_day_80,withholding_ending,vaccination_due,calf_behind_target,calf_due_weaning'],
            'conditions' => ['sometimes', 'array'],
            'channels' => ['sometimes', 'array'],
            'channels.*' => ['sometimes', 'string', 'in:in_app,sms,email'],
            'recipients' => ['sometimes', 'array'],
            'recipients.*' => ['sometimes', 'string', 'in:Owner,Manager,Milker,AI Tech,Vet,Viewer'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
