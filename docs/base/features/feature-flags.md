---
id: BASE-005
name: Feature Flags
status: implemented
---

# Feature Flags

A feature flag is a layer **above** RBAC + Plan. Access to a module requires ALL:
`Pennant ON` AND `Plan feature allowed` AND `Role permission` AND `Plan permission
allowed` (for domain permissions). When a feature is off, access is denied to
everyone — true global kill switch.

Implemented with **Laravel Pennant** (`laravel/pennant`).

## Storage
- DB table `features` (Pennant's own migration) stores resolved flag values.
- Flag declarations + labels live in `config/pennant.php` (`features` map).
- Declared in `AppServiceProvider::boot()` via `Feature::define($slug, fn () => true)`.
- Default state is ON; toggling writes the resolved value to the DB store.
- No config/deploy needed to toggle; done from the UI.

## Checking a flag
```php
Feature::active('users');   // true/false; missing feature => false (fail-closed, built into Pennant)
```
Blade: `@feature('users') ... @endfeature` (native Pennant directive).

## Enforcement
- Route middleware `feature:{slug}` (`App\Http\Middleware\EnsureFeatureEnabled`),
  stacked BEFORE `can:{perm}`:
  ```php
  Route::resource('users', ...)->middleware(['feature:users', 'can:user.view']);
  ```
- Pennant OFF → 404 for **everyone** (true kill-switch, including `feature.manage`
  holders). Managers reach disabled modules only by re-enabling from `/features`.
  **Pennant OFF blocks super-admin too** — the platform-level superadmin bypass
  applies to Plan entitlement only, NOT to the Pennant global kill switch.
- Pennant ON + Plan feature ON → proceeds to permission gate.
- Pennant ON + Plan feature OFF → denied by Plan entitlement (Gate `before`
  returns false → 403). Super-admin bypasses this (Gate `before` returns null
  for super-admin via BypassService).
- A flag off 404s the route and hides its sidebar entry.

## Management UI
- `/features` lives **under the Settings submenu** (gated by `feature.manage` permission; `staff` + `super-admin` get it).
- Lists all flags grouped by `group` from `config/pennant.php`, rendered with
  `ui('feature_group_'.$group)` in `resources/views/settings/features/index.blade.php`.
- Group labels live in `lang/{en,id}/ui.php`: `feature_group_access`, `feature_group_monitoring`,
  `feature_group_settings`, `feature_group_billing`, `feature_group_workspace`, `feature_group_other`.
- Each flag shows a **toggle switch** (auto-confirms via modal) for enable/disable.
- A `feature.manage` holder can toggle flags (re-enable a disabled module from `/features`), but cannot access a module whose flag is currently OFF — the kill switch applies to everyone.

## Known flags
- `users`, `roles`, `permissions`, `audit`, `sessions`, `api-tokens`, `translations`, `logs`, `telescope`, `periscope`, `plans`, `billing`
  (declared in `config/pennant.php`).

## Gate
- Every module route is wrapped in `feature:` — RED if a module route lacks it.
- A flag off must block the route AND hide its sidebar entry.
