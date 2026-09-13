<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates system-wide settings (default locale + registration toggle +
 * license mode + default plan/role). Authz is enforced on the route via
 * `can:feature.manage` middleware — authorize() mirrors it for defense-in-depth.
 */
class SystemSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('feature.manage');
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'locale_default' => ['required', 'string', Rule::in(config('app.available_locales', ['en', 'id']))],
            'registration_enabled' => ['boolean'],
            'license_mode' => ['required', 'string', 'in:global,per_user'],
            'default_plan' => ['nullable', 'string', 'exists:plans,slug'],
            'default_role' => ['nullable', 'string', 'exists:roles,name'],
        ];
    }
}
