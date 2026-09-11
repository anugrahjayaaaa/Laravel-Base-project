---
id: BASE-012
name: Permission Sync Design
status: deprecated
---

# Permission Sync Design (DEPRECATED — No-Op)

> **Status:** This design document is **deprecated**. `syncPermissionsForPlan()`
> was previously used to copy Plan permissions to all Users. It is now a
> **no-op** (see CHALLENGE 2). Permissions are resolved at runtime via
> Role ∩ Plan, not synced into Users.

## Original Problem (historical)

- Staff users saw Access Management / Monitoring menus despite plan having
  limited features
- Permissions came from role assignment, not plan — disconnected

## Original Solution (historical, no longer used)

When a plan was created/updated, permissions were synced to ALL users based on
the plan's `features` array + `limits.allowed_permissions`.

### Historical Flow

```
PlanController::update()
  → PlanService::syncPermissionsForPlan($plan)
    → foreach User: $user->syncPermissions($derivedPermissions)
```

### Historical Rules

- Global mode: sync to ALL users (one instance plan)
- Per-user mode: per-user sync happens via LicenseService when license assigned
- Permission names derived from `Permission::featureOf($perm->name)`

## Current Architecture (replaces the above)

Permissions are resolved at runtime — NOT synced:

```
Effective Permission = User.Role.Permission AND Plan.allows(Permission)
```

- **Plan.allowed_permissions** = commercial entitlement ceiling (runtime check)
- **Role.permissions** = persistent assignment (never mutated by Plan changes)
- **Gate `before`** = Plan entitlement + spatie role check
- **RoleController::filterPermissions** (web + API) = server-side enforcement
  on role *mutation* (what permissions a subscriber may assign)

`syncPermissionsForPlan()` is called for backward compatibility but does nothing.

## Files

- `app/Services/PlanService.php` — `syncPermissionsForPlan()` (no-op)
- `app/Services/PlanService.php` — `allows()` / `allowedPermissions()` (runtime check)
- `app/Providers/AppServiceProvider.php` — Gate `before` (Plan boundary)
- `app/Http/Controllers/RoleController.php` — `filterPermissions()` (web)
- `app/Http/Controllers/Api/RoleApiController.php` — `filterPermissions()` (API)
