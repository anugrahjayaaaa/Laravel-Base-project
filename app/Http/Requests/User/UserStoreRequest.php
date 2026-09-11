<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates creating a user (admin).
 * Authz handled by route middleware (can:user.create).
 */
class UserStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('user.create');
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|max:255|unique:users,email',
            'phone' => ['nullable', 'string', 'max:255', 'regex:/^\+?[0-9\s\-\(\)]+$/', 'unique:users,phone'],
            'password' => 'required|string|min:12|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
        ];
    }
}
