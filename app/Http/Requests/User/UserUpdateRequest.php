<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates updating a user (admin). Ignores the user being edited on unique checks.
 * Authz handled by route middleware (can:user.update).
 */
class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.edit');
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        $userId = $this->route('user')->id;

        return [
            'name' => 'required|string|max:255',
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($userId)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^\+?[0-9\s\-\(\)]+$/', Rule::unique('users', 'phone')->ignore($userId)],
            'password' => 'nullable|string|min:12|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ];
    }
}
