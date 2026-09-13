<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

/**
 * Sets the app locale from the session (fallback to config app.locale).
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        // ponytail: fallback chain — session (per-user override) → Setting::locale_default (admin-set) → config app.locale
        $locale = Session::get('locale');
        if (! $locale) {
            $locale = Setting::get('locale_default', config('app.locale'));
        }
        if (! in_array($locale, config('app.available_locales', ['en']))) {
            $locale = config('app.locale');
        }
        App::setLocale($locale);

        return $next($request);
    }
}
