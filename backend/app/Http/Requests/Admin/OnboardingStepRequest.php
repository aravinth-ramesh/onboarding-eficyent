<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $stepId = $this->route('onboarding_step')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('onboarding_steps')->ignore($stepId)],
            'description' => ['nullable', 'string'],
            'component_key' => ['required', 'string', 'max:100'],
            'order' => ['integer', 'min:0'],
            'is_active' => ['boolean'],
            'config' => ['nullable', 'array'],
        ];
    }
}
