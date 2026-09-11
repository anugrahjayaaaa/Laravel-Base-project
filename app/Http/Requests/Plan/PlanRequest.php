<?php

namespace App\Http\Requests\Plan;

use App\Models\Permission;
use App\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Validates plan create/update. Authz via route middleware (can:feature.manage).
 * Cross-field guards (max_features cap, permission↔feature consistency) live in
 * `after()` so bypass attempts from the client are rejected server-side.
 */
class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('feature.manage');
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        $planId = $this->route('plan')?->id ?? 'NULL';

        return [
            'name' => 'required|string|max:120',
            'slug' => "nullable|string|alpha_dash|unique:plans,slug,{$planId},id",
            'price_monthly' => 'required|numeric|min:0',
            'billing_period' => 'required|in:monthly,lifetime',
            'is_active' => 'boolean',
            'max_members' => 'nullable|integer|min:0',
            'max_roles' => 'nullable|integer|min:0',
            'max_permissions' => 'nullable|integer|min:0',
            'max_features' => 'nullable|integer|min:0',
            'max_storage_mb' => 'nullable|integer|min:0',
            'allowed_permissions' => 'array',
            'allowed_permissions.*' => 'string|exists:permissions,name',
            'features' => 'nullable|array',
            'features.*' => 'string|in:'.implode(',', array_keys(config('pennant.features', []))),
        ];
    }

    /** Build the Plan attributes array (limits JSON + features). */
    public function toPlanData(): array
    {
        $limits = [];
        foreach (array_keys(Plan::LIMIT_KEYS) as $key) {
            if ($this->filled($key)) {
                $limits[$key] = (int) $this->input($key);
            }
        }
        $limits['allowed_permissions'] = $this->input('allowed_permissions', []);

        return [
            'name' => $this->input('name'),
            'slug' => $this->input('slug') ?: Str::slug($this->input('name')),
            'price_monthly' => $this->input('price_monthly'),
            'billing_period' => $this->input('billing_period'),
            'is_active' => $this->boolean('is_active'),
            'limits' => $limits,
            'features' => $this->input('features', []),
        ];
    }

    /** Cross-field guards that can't be expressed as single-field rules. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $features = (array) $this->input('features', []);
                $maxFeatures = (int) $this->input('max_features', 0);

                if ($maxFeatures > 0 && count($features) > $maxFeatures) {
                    $validator->errors()->add(
                        'features',
                        __('messages.feature_limit_exceeded', ['max' => $maxFeatures])
                    );
                }

                $allowed = (array) $this->input('allowed_permissions', []);
                if ($allowed !== []) {
                    $valid = Permission::whereIn('name', $allowed)
                        ->get()
                        ->filter(fn ($p) => in_array(Permission::featureOf($p->name), $features, true))
                        ->pluck('name')
                        ->all();
                    if (count($valid) !== count($allowed)) {
                        $validator->errors()->add(
                            'allowed_permissions',
                            __('messages.permission_feature_mismatch')
                        );
                    }
                }
            },
        ];
    }
}
