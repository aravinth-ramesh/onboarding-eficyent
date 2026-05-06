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
            'type' => ['required', Rule::in(['text', 'radio', 'date', 'select', 'multi_select', 'textarea', 'number', 'file', 'table'])],
            'options' => ['nullable', 'array'],
            'is_required' => ['boolean'],
            'order' => ['integer', 'min:0'],

            // Per-question validation metadata. The frontend parses these to
            // produce field-level errors; only the keys relevant to a given
            // question type are honoured.
            'validation_rules' => ['nullable', 'array'],
            'validation_rules.pattern' => ['nullable', 'string', 'max:1000'],
            'validation_rules.pattern_message' => ['nullable', 'string', 'max:255'],
            'validation_rules.min_length' => ['nullable', 'integer', 'min:0'],
            'validation_rules.max_length' => ['nullable', 'integer', 'min:0'],
            'validation_rules.min' => ['nullable', 'numeric'],
            'validation_rules.max' => ['nullable', 'numeric'],
            'validation_rules.allow_past' => ['nullable', 'boolean'],
            'validation_rules.allow_future' => ['nullable', 'boolean'],
            'validation_rules.allow_today' => ['nullable', 'boolean'],
            'validation_rules.min_date' => ['nullable', 'date'],
            'validation_rules.max_date' => ['nullable', 'date'],

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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Option labels/values are only required for choice-style questions.
            $type = $this->input('type');
            $options = $this->input('options');
            if (in_array($type, ['radio', 'select', 'multi_select'], true) && is_array($options)) {
                foreach ($options as $idx => $opt) {
                    if (! is_array($opt) || ! isset($opt['label']) || $opt['label'] === '') {
                        $validator->errors()->add("options.$idx.label", 'Each option must have a label.');
                    }
                    if (! is_array($opt) || ! isset($opt['value']) || $opt['value'] === '') {
                        $validator->errors()->add("options.$idx.value", 'Each option must have a value.');
                    }
                }
            }

            // Sanity-check pattern (must be a valid PCRE regex).
            $pattern = data_get($this->input('validation_rules'), 'pattern');
            if (is_string($pattern) && $pattern !== '') {
                set_error_handler(fn () => null);
                $valid = @preg_match('/' . str_replace('/', '\\/', $pattern) . '/', '') !== false;
                restore_error_handler();
                if (! $valid) {
                    $validator->errors()->add('validation_rules.pattern', 'The regex pattern is invalid.');
                }
            }
        });
    }
}
