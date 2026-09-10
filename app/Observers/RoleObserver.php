<?php

namespace App\Observers;

use App\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class RoleObserver
{
    /**
     * Audit `role_created` only here as a NON-HTTP fallback (e.g. tinker/CLI
     * seeders). HTTP creation path is owned by `RoleController`, which emits
     * the same event with full request context to avoid duplicates.
     *
     * Other mutations (updated/deleted/restored/forceDeleted) are handled
     * exclusively by the controller — observer intentionally empty to prevent
     * double-audit rows on HTTP paths.
     */

    public function created(Role $role): void
    {
        // ponytail: non-HTTP fallback only; controller emits this for web/API.
        $causer = Auth::user();
        if ($causer) {
            return; // already handled by controller
        }
        activity()->withProperties([
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ])->performedOn($role)->log('role_created');
    }

    public function updated(Role $role): void
    {
        // ponytail: controller-first audit; no-op to avoid duplicate rows.
    }

    public function deleted(Role $role): void
    {
        // ponytail: controller emits role_deleted.
    }

    public function restored(Role $role): void
    {
        // ponytail: controller emits role_restored.
    }

    public function forceDeleted(Role $role): void
    {
        // ponytail: controller emits role_force_deleted.
    }
}
