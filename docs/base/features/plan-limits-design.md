---
id: BASE-013
name: Plan Limits Design
status: implemented
---

# Plan Limits Design

## Problem
- `license_mode` setting exists (global/per_user) but does not affect the plan form UI
- Free plan limits finalized: all 0, features [] (user decision)
- Per-user mode needs different display logic: feature access only, no capacity limits

## Solution

### 1. Conditional display logic (blade)
`plans/form.blade.php` keeps the capacity limits section visible for all modes
(can be adjusted later for per-user-only access control).

For per-user license_mode, add:
```blade
@if (Setting::get('license_mode', 'global') === 'per_user')
    <div class="alert alert-info">Per-user mode: limits apply per-user via individual licenses.</div>
@endif
```

### 2. Active LIMIT_KEYS
Final limit schema (only keys with implemented consumers in the current base):
```php
// Plan::LIMIT_KEYS (app/Models/Plan.php)
'max_members', 'max_roles', 'max_permissions', 'max_features', 'max_storage_mb',
'allowed_permissions'
```
Removed: `max_projects` (never implemented — no project CRUD in routes),
`can_create_roles` (role creation now gates on the `role.create` permission,
granted via the `roles` feature).

### 3. PlanService
|`PlanService::for()` resolves the user license plan_slug in per_user mode.
|`syncPermissionsForPlan()` is a **no-op** — permissions are resolved at runtime
> via Role ∩ Plan (`PlanService::allows()`). See `docs/base/features/permission-sync-design.md`.

### 4. Seeded plans (reference data)
`database/seeders/PlanSeeder.php` seeds three tiers with deterministic
`updateOrCreate` keyed on slug. Limit schema is exactly the 6 active keys
above; `0` means unlimited for numeric keys; `allowed_permissions` empty
means deny-by-default.

```php
// free — minimal: all limits 0, no features
'limits' => ['max_features'=>0,'max_members'=>0,'max_storage_mb'=>0,'max_permissions'=>0,'max_roles'=>0,'allowed_permissions'=>[]],
'features' => [],

// pro — 99000, 5 members, api-tokens+audit+telescope (test fixture baseline)
'limits' => ['max_features'=>3,'max_members'=>5,'max_storage_mb'=>2000,'max_permissions'=>10,'max_roles'=>3,'allowed_permissions'=>[]],
'features' => ['api-tokens','audit','telescope'],

// enterprise — 499000, effectively unlimited (0), all base pennant feature flags
'limits' => ['max_features'=>0,'max_members'=>0,'max_storage_mb'=>0,'max_permissions'=>0,'max_roles'=>0,'allowed_permissions'=>[]],
'features' => ['users','roles','permissions','audit','logs','telescope','periscope','sessions','api-tokens','translations','features','plans','billing'],
```
