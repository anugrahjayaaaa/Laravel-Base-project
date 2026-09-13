<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
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
