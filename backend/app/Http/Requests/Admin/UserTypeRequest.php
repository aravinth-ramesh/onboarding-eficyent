<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $typeId = $this->route('user_type')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('user_types')->ignore($typeId)],
            'description' => ['nullable', 'string'],
            'has_subcategories' => ['boolean'],
            'is_active' => ['boolean'],
            'order' => ['integer', 'min:0'],
        ];
    }
}
