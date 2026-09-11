<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the password reset form (token + new password).
 */
class PasswordResetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint (token proves ownership)
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return [
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            // Strong password: min 12 chars, must include uppercase, lowercase, digit, symbol (doc auth.md §Password rules)
            'password' => ['required', 'string', 'min:12', 'confirmed', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.?<>\/]/'],
            'password_confirmation' => 'required|string',
        ];
    }

    /** @return array<string,string> */
    public function messages(): array
    {
        return [
            'password.required' => __('messages.password_required'),
            'password.min' => __('messages.password_min', ['min' => 12]),
            'password.confirmed' => __('messages.password_confirmed_mismatch'),
            'password.regex' => __('messages.password_strong_required'),
        ];
    }
}
