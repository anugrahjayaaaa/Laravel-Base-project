<?php

namespace App\Observers;

use App\Models\User;
use Illuminate\Support\Facades\Request;

class UserObserver
{
    public function created($user)
    {
        $payload = [
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ];

        if (auth()->check()) {
            activity()->causedBy(auth()->user())->withProperties($payload)->performedOn($user)->log('user_created');
        } else {
            activity()->performedOn($user)->withProperties($payload)->log('user_created');
        }
    }

    public function updated($user)
    {
        $dirty = $user->getDirty();
        unset($dirty['password'], $dirty['remember_token']); // ponytail: never log secrets
        $old = [];
        foreach ($dirty as $k => $v) {
            $old[$k] = $user->getOriginal($k);
        }
        activity()->causedBy(auth()->user())->withProperties([
            'ip' => Request::ip(), 'user_agent' => Request::userAgent(),
            'old' => $old, 'new' => $dirty,
        ])->performedOn($user)->log('user_updated');
    }

    public function deleted($user)
    {
        activity()->causedBy(auth()->user())->withProperties([
            'ip' => Request::ip(), 'user_agent' => Request::userAgent(),
        ])->performedOn($user)->log('user_deleted');
    }

    public function restored($user)
    {
        activity()->causedBy(auth()->user())->withProperties([
            'ip' => Request::ip(), 'user_agent' => Request::userAgent(),
        ])->performedOn($user)->log('user_restored');
    }

    /**
     * Log a permanent (hard) delete of a user to the audit trail.
     *
     * @param  User  $user  The soft-deleted-then-force-deleted user
     * @return void
     *
     * @details Writes a 'user_force_deleted' activity row into DB table `activity_log`
     * (spatie/activitylog). Unlike `deleted()` (soft delete), this is unrecoverable.
     */
    public function forceDeleted($user)
    {
        activity()->causedBy(auth()->user())->withProperties([
            'ip' => Request::ip(), 'user_agent' => Request::userAgent(),
        ])->performedOn($user)->log('user_force_deleted');
    }
}
