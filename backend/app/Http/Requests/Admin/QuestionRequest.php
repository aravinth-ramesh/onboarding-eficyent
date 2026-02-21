<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_group_id' => ['required', 'exists:question_groups,id'],
            'label' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file'])],
            'options' => ['nullable', 'array'],
            'options.*.label' => ['required_with:options', 'string'],
            'options.*.value' => ['required_with:options', 'string'],
            'is_required' => ['boolean'],
            'order' => ['integer', 'min:0'],
            'validation_rules' => ['nullable', 'array'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'help_text' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'type_mappings' => ['nullable', 'array'],
            'type_mappings.*.user_type_id' => ['required', 'exists:user_types,id'],
            'type_mappings.*.user_type_subcategory_id' => ['nullable', 'exists:user_type_subcategories,id'],
            'type_mappings.*.order' => ['nullable', 'integer'],
            'type_mappings.*.is_required' => ['nullable', 'boolean'],
        ];
    }
}
