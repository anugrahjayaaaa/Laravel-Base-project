<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates self-service registration (public when enabled).
 * Gate is enforced at the route level via Setting::get('registration_enabled').
 *
 * @see docs/auth.md §Self-service
 */
class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint when registration is enabled (checked in route middleware)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^\+?[0-9\s\-\(\)]+$/', 'unique:users,phone'],
            // Strong password: min 12 chars with upper/lower/number/symbol (doc auth.md §Password rules)
            'password' => ['required', 'string', 'min:12', 'confirmed', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/', 'regex:/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.?<>\/]/'],
        ];
    }

    /** Custom messages for strong password so the user knows exactly what is missing. */
    public function messages(): array
    {
        return [
            'password.required' => __('messages.password_required'),
            'password.min' => __('messages.password_min', ['min' => 12]),
            'password.confirmed' => __('messages.password_confirmed_mismatch'),
            'password.regex' => __('messages.password_strong_required'),
            'phone.regex' => __('messages.phone_invalid'),
        ];
    }
}
