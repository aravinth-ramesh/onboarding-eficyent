<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConditionalRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question_id' => ['required', 'exists:questions,id'],
            'parent_question_id' => ['required', 'exists:questions,id', 'different:question_id'],
            'comparison_type' => ['required', Rule::in([
                'equals', 'not_equals', 'contains', 'not_contains',
                'greater_than', 'less_than', 'in', 'not_in',
                'is_empty', 'is_not_empty',
            ])],
            'trigger_value' => ['nullable', 'string'],
            'action' => [Rule::in(['show', 'hide'])],
            'logical_operator' => [Rule::in(['and', 'or'])],
            'is_active' => ['boolean'],
        ];
    }
}
