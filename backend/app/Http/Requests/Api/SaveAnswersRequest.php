<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SaveAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('onboarding_uploads.max_file_size_kb', 5120);
        $allowedMimes = implode(',', config('onboarding_uploads.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'docx']));

        return [
            // Non-file answers (optional when only file answers are submitted)
            'answers' => ['sometimes', 'array'],
            'answers.*.question_id' => ['required', 'exists:questions,id'],
            'answers.*.value' => ['present'],

            // File answers (optional)
            'file_answers' => ['sometimes', 'array'],
            'file_answers.*.question_id' => ['required', 'exists:questions,id'],
            'file_answers.*.file' => ['required', 'file', "mimes:{$allowedMimes}", "max:{$maxSize}"],

            // Per-cell files for table-type answers (optional)
            'table_file_answers' => ['sometimes', 'array'],
            'table_file_answers.*.question_id' => ['required', 'exists:questions,id'],
            'table_file_answers.*.row_index' => ['required', 'integer', 'min:0'],
            'table_file_answers.*.column_key' => ['required', 'string', 'max:255'],
            'table_file_answers.*.file' => ['required', 'file', "mimes:{$allowedMimes}", "max:{$maxSize}"],
        ];
    }

    public function messages(): array
    {
        $maxSizeMb = round(config('onboarding_uploads.max_file_size_kb', 5120) / 1024, 1);

        return [
            'file_answers.*.file.mimes' => 'Only allowed file types: ' . implode(', ', config('onboarding_uploads.allowed_mimes', [])) . '.',
            'file_answers.*.file.max' => "Each file must not exceed {$maxSizeMb} MB.",
            'table_file_answers.*.file.mimes' => 'Only allowed file types: ' . implode(', ', config('onboarding_uploads.allowed_mimes', [])) . '.',
            'table_file_answers.*.file.max' => "Each file must not exceed {$maxSizeMb} MB.",
        ];
    }

    /**
     * Ensure at least one of answers or file_answers is present.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (
                empty($this->input('answers'))
                && empty($this->file('file_answers')) && empty($this->input('file_answers'))
                && empty($this->file('table_file_answers')) && empty($this->input('table_file_answers'))
            ) {
                $validator->errors()->add('answers', 'At least one answer or file must be provided.');
            }
        });
    }
}
