<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Matches SendOtpRequest — a dotless domain is not a real address (EOP-2).
            'email' => ['required', 'email:filter', 'regex:/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
