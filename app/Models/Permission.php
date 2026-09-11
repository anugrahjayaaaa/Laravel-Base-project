<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Application Permission model.
 *
 * Extends spatie's Permission and adds SoftDeletes so permissions can be trashed/restored.
 * Referenced by config/permission.php (models.permission) so all permission queries/mutations
 * route through this class (not the spatie base) — required for the RBAC observers
 * (App\Observers\PermissionObserver) to fire.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $deleted_at (SoftDeletes)
 */
class Permission extends SpatiePermission
{
    use SoftDeletes;

    /**
     * Feature slug a permission belongs to. Derived dynamically from the configured
     * Pennant flags (config/pennant.features) so adding a feature needs no code change
     * here. The DB permission prefix is singular (user.*) while feature flags are
     * plural (users); we match by prefix OR its plural form.
     */
    public static function featureOf(string $name): ?string
    {
        $prefix = explode('.', $name, 2)[0];
        $flags = array_keys(config('pennant.features', []));

        if (in_array($prefix, $flags, true)) {
            return $prefix;
        }
        // ponytail: singular permission prefix -> plural feature flag (user -> users)
        if (in_array(Str::plural($prefix), $flags, true)) {
            return Str::plural($prefix);
        }

        return $prefix;
    }
}
