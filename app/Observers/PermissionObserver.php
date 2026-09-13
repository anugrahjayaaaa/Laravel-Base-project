<?php

namespace App\Observers;

use App\Models\Permission;

class PermissionObserver
{
    /**
     * Audit logs for permission mutations are now emitted from controllers
     * (`PermissionController` / `PermissionApiController`) where HTTP request
     * context is available. Observer removed to avoid duplicate activity_log rows.
     */
}
