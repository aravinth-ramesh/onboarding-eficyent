<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Bare `email` is FILTER_VALIDATE_EMAIL, which accepts a dotless
            // domain like "aaaa@a" — an OTP would be queued to an
            // undeliverable address. Require a real domain (EOP-2).
            'email' => ['required', 'email:filter', 'regex:/^[^@\s]+@[^@\s.]+(\.[^@\s.]+)+$/', 'max:255'],
        ];
    }
}
