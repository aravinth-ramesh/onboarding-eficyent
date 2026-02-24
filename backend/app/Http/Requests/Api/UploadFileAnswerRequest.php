<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxSize = config('onboarding_uploads.max_file_size_kb', 5120);
        $allowedMimes = implode(',', config('onboarding_uploads.allowed_mimes', ['pdf', 'jpg', 'jpeg', 'png', 'docx']));
        $maxFiles = config('onboarding_uploads.max_files_per_question', 10);

        return [
            'question_id' => ['required', 'exists:questions,id'],
            'files' => ['required', 'array', 'min:1', "max:{$maxFiles}"],
            'files.*' => ["required", "file", "mimes:{$allowedMimes}", "max:{$maxSize}"],
        ];
    }

    public function messages(): array
    {
        $maxSizeMb = round(config('onboarding_uploads.max_file_size_kb', 5120) / 1024, 1);
        $allowedMimes = implode(', ', config('onboarding_uploads.allowed_mimes', []));

        return [
            'files.*.mimes' => "Only the following file types are allowed: {$allowedMimes}.",
            'files.*.max' => "Each file must not exceed {$maxSizeMb} MB.",
            'files.max' => 'Too many files. Maximum allowed: ' . config('onboarding_uploads.max_files_per_question', 10) . '.',
        ];
    }
}
