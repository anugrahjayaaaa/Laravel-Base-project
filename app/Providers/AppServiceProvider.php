<?php

namespace App\Providers;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Observers\PermissionObserver;
use App\Observers\RoleObserver;
use App\Observers\UserObserver;
use App\Services\PlanService;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;
use Sentry\Sentry;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // CHALLENGE 3: Register Plan permission boundary BEFORE spatie does.
        // Register() runs before all boot() methods, so our afterResolving listener
        // (index 0) executes before spatie's callAfterResolving (index 1) when the
        // Gate is first resolved. This guarantees our Plan check runs first;
        // returning false short-circuits before spatie's role check.
        // Fail closed: Plan entitlement is necessary (not sufficient).
        $this->app->afterResolving(Gate::class, function (Gate $gate) {
            $gate->before(function ($user, $ability) {
                if (! $user) {
                    return null;
                }

                // Only check known permission names (avoid overhead on policy abilities)
                if (Permission::where('name', $ability)->exists()) {
                    // RBAC system permissions (role.*, permission.*) are governed by
                    // the user's role, not the plan tier — the plan boundary only caps
                    // assignable domain permissions (enforced separately in RoleController::filterPermissions).
                    if (str_starts_with($ability, 'role.') || str_starts_with($ability, 'permission.')) {
                        return null;
                    }

                    // SUPERADMIN: platform-level super-admins (via the 'super-admin' role)
                    // bypass the Plan entitlement boundary. They are still subject to
                    // Pennant feature flags (checked at route middleware level, not here).
                    if ($user->isSuperAdmin()) {
                        return null;
                    }

                    if (! PlanService::for($user)->allows($ability) && Feature::active('plans')) {
                        return false;
                    }
                }

                return null;
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ponytail: module flags are GLOBAL (not per-user); force a fixed scope so
        // Feature::active/deactivate behave app-wide, like the old DB table.
        Feature::resolveScopeUsing(fn () => 'global');

        // ponytail: declare every module feature flag so Pennant resolves it;
        // default ON. DB store persists toggles from the /features UI.
        foreach (array_keys(config('pennant.features', [])) as $slug) {
            Feature::define($slug, fn () => true);
        }

        Paginator::useBootstrap();
        LengthAwarePaginator::useBootstrap();

        User::observe(UserObserver::class);
        Role::observe(RoleObserver::class);
        Permission::observe(PermissionObserver::class);

        // ponytail: Sentry DSN-gated; no-op locally when DSN empty
        if (class_exists(Sentry::class) && config('sentry.dsn')) {
            Sentry::init([
                'dsn' => config('sentry.dsn'),
                'release' => config('app.version'),
                'traces_sample_rate' => (float) config('sentry.traces_sample_rate', 0.2),
            ]);
        }

        // Header notifications: native Laravel notifications (database channel)
        View::composer('layouts.app', function ($view) {
            if (auth()->check()) {
                $user = auth()->user();
                $items = $user->notifications()->latest()->limit(5)->get();
                $view->with('notifications', [
                    'items' => $items,
                    'unread' => $user->unreadNotifications()->count(),
                ]);
            } else {
                $view->with('notifications', ['items' => collect(), 'unread' => 0]);
            }
        });
    }
}
