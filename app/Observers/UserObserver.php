<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class UserObserver
{
    /**
     * Audit logs for user mutations are handled in controllers (`UserController`
     * / `UserApiController`) for HTTP paths. This observer retains ONLY the
     * `created` event as a NON-HTTP fallback (e.g. tinker / CLI seeders),
     * guarded so it does not double-log when a controller already did.
     */

    public function created(User $user): void
    {
        // ponytail: non-HTTP fallback only; controller emits user_created for web/API.
        if (Auth::user()) {
            return; // already handled by controller
        }
        activity()->withProperties([
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ])->performedOn($user)->log('user_created');
    }

    public function updated(User $user): void
    {
        // ponytail: controller-first audit; controller emits user_updated.
    }

    public function deleted(User $user): void
    {
        // ponytail: controller emits user_deleted.
    }

    public function restored(User $user): void
    {
        // ponytail: controller emits user_restored.
    }

    public function forceDeleted(User $user): void
    {
        // ponytail: controller emits user_force_deleted.
    }
}
