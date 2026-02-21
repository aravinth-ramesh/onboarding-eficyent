<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SetUserTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_type_id' => ['required', 'exists:user_types,id'],
            'subcategory_id' => ['nullable', 'exists:user_type_subcategories,id'],
        ];
    }
}
