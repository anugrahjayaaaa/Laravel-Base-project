<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\SystemSettingsRequest;
use App\Models\Plan;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * System-wide settings page (default language + registration toggle).
 *
 * Gated by `feature.manage` permission on the route (see routes/web.php).
 * Stores boolean-ish flags as Setting rows:
 *  - locale_default  : default app locale (en|id)
 *  - registration_enabled : whether /register is accessible
 */
class SettingsController extends Controller
{
    /** Show the system settings page. */
    public function index(): View
    {
        return view('settings.system', [
            'defaultLocale' => Setting::get('locale_default', config('app.locale', 'en')),
            'registrationEnabled' => (bool) Setting::get('registration_enabled', false),
            'licenseMode' => Setting::get('license_mode', 'global'),
            'defaultPlan' => Setting::get('default_plan', null),
            'defaultRole' => Setting::get('default_role', null),
            'availableLocales' => config('app.available_locales', ['en', 'id']),
            'plans' => Plan::where('is_active', true)->orderBy('name')->get(['slug', 'name']),
            'roles' => Role::whereNull('deleted_at')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Update system settings (default locale + registration toggle). */
    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();

        Setting::set('locale_default', $data['locale_default']);
        Setting::set('registration_enabled', $data['registration_enabled']);
        Setting::set('license_mode', $data['license_mode']);
        Setting::set('default_plan', $data['default_plan']);
        Setting::set('default_role', $data['default_role']);

        return back()->with('status', __('messages.settings_updated'));
    }
}
