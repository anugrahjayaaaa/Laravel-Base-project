<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * @param object  $model
     * @param string  $action
     * @param object|null $causer
     * @param array   $properties
     * @return void
     */
    private function audit(object $model, string $action, ?object $causer = null, array $properties = []): void
    {
        $causer ??= Auth::user();

        if (! $causer) {
            return;
        }

        activity()
            ->causedBy($causer)
            ->performedOn($model)
            ->withProperties(array_merge([
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ], $properties))
            ->log($action);
    }

    /**
     * @param string  $action
     * @param object|null $causer
     * @param array   $properties
     * @return void
     */
    private function auditAction(string $action, ?object $causer = null, array $properties = []): void
    {
        $causer ??= Auth::user();

        if (! $causer) {
            return;
        }

        activity()
            ->causedBy($causer)
            ->withProperties(array_merge([
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ], $properties))
            ->log($action);
    }
}
