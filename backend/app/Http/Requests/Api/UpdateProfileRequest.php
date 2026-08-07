<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // A person's name is letters (plus spaces, apostrophes, hyphens,
            // dots); a job title may carry digits but must contain letters.
            // Previously both accepted "12345" or "<script>" (EOP-5, EOP-7).
            'name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s.\'-]+$/u'],
            'position' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\p{N}\s.\'\/&-]+$/u', 'regex:/\p{L}/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Enter a valid name — letters only, without digits or symbols.',
            'position.regex' => 'Enter a valid position — it must contain letters and no special characters.',
        ];
    }
}
