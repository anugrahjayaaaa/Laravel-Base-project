<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the self-service profile update (name + phone).
 */
class ProfileUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // own profile (auth required by route)
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        $userId = $this->user()->id;

        return [
            'name' => 'required|string|max:255',
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^\+?[0-9\s\-\(\)]+$/', Rule::unique('users', 'phone')->ignore($userId)],
        ];
    }
}
