<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fail-closed gate for self-service registration.
 * Blocks the /register routes (404) unless Setting::get('registration_enabled')
 * is truthy. 404 (not 403) so attackers can't even confirm the route exists.
 */
class RegistrationEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::get('registration_enabled', false)) {
            abort(404);
        }

        return $next($request);
    }
}
