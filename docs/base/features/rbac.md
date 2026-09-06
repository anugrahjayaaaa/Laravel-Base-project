---
id: BASE-003
name: RBAC (Authorization Model)
status: implemented
---

# Authorization (Dynamic RBAC)

Uses `spatie/laravel-permission`. Roles & permissions are created/edited via UI (dynamic).

## Model
- `permissions`: granular actions (`user.view`, `user.create`, `role.edit`).
- `roles`: a set of permissions; a user may have multiple roles.
- **Super-admin role**: `super-admin` — platform-level role seeded with ALL permissions via
  `syncPermissions(Permission::all())`. This is NOT a code bypass — it is a Role with full
  permissions. **Platform-level Plan Entitlement Override**: super-admin role assignment
  signals a platform operator (not a commercial subscriber). `User::isSuperAdmin()`
  (role-based check) is consulted by the runtime authorization layer in
  `App\Providers\AppServiceProvider::register()`. When true, the runtime authorization
  layer **bypasses Plan permission AND Plan feature entitlement**.
  This applies only to the Plan boundary — Pennant is checked separately and STILL
  applies (Pennant OFF → 404 even for super-admin).
- Role & Permission are **custom models** (`App\Models\Role`, `App\Models\Permission`)
  extending spatie with `SoftDeletes`; `config/permission.php` points spatie to them.
  Always import the custom models, never `Spatie\Permission\Models\*` directly.

## Actions & permissions
- Each resource has: `*.view`, `*.create`, `*.edit`, `*.delete`,
  `*.restore` (soft-deleted → active), `*.force-delete` (permanent, trashed only).
- Restore/force-delete buttons appear only when the row is trashed AND the caller
  holds `*.restore` / `*.force-delete`.

## Management pages (required)
1. Roles: list, create, edit, delete, attach/detach permissions.
2. Permissions: list, create, edit, delete (or seed only).
3. User ↔ Role: assign from user management.

## Enforcement
- **Route-level middleware**, never in a controller constructor:
  ```php
  Route::resource('users', UserController::class)->middleware(['feature:users', 'can:user.view']);
  ```
- `feature:{slug}` runs FIRST (Pennant kill switch → 404 if off), then `can:{perm}`
  (Role permission + Plan entitlement via Gate `before`). Order matters: Pennant OFF
  must 404 before the permission gate can 403 (see `docs/base/features/feature-flags.md`).
- `can:{perm}` is Laravel's built-in authorization middleware (spatie
  permission registered as the gate). Do **not** use a custom `permission:`
  middleware, and do **not** call `$this->middleware()` inside a controller
  `__construct()` — see `docs/base/conventions/coding.md` §Authorization.
- Ownership checks (if needed) go in a Form Request `authorize()` or a Policy,
  not in the controller body. Current modules gate purely by permission; no
  per-resource Policy is registered.
- Every role/permission change → audit trail.
- **Effective permission** = Role grants permission AND Plan allows permission (see `docs/base/features/licensing-and-billing.md` §11). Plan alone never grants; Role alone never bypasses Plan.
- **Super-admin bypass**: `User::isSuperAdmin()` (true when the user holds the `super-admin`
  Role — NOT username-based) is checked by the runtime authorization layer in
  `App\Providers\AppServiceProvider::register()`. When true, the runtime authorization
  layer **bypasses Plan permission AND Plan feature entitlement**.
  This applies only to the Plan boundary — Pennant is checked separately and STILL
  applies (Pennant OFF → 404 even for super-admin).
- **Management permissions** (`role.*`, `permission.*`) are exempt from the Plan
  boundary Gate check — they are governed by Role assignment, not Plan tier.
  However, `filterPermissions()` in `RoleController` / `RoleApiController`
  enforces that a subscriber can only ASSIGN permissions within the Plan's
  `allowed_permissions` when creating/editing roles. This prevents bypassing
  Plan entitlement via Role mutation.
- **Feature flags** sit above RBAC + Plan: a module route requires Pennant ON,
  Plan feature allowed, AND Role permission. Permission alone is not enough —
  the feature must be enabled, or the route 404s and its sidebar item hides.

## Initial seed
|- Roles `super-admin`, `admin`, `staff` + default permissions.
|- First seeded user = super-admin (username `superadmin`, assigned `super-admin` role with ALL permissions). The username is seed data only — `isSuperAdmin()` checks the Role, never the username.

## Translations (Settings)
- Permissions: `translation.view`, `translation.edit` (edit-only module — no create/delete routes).
- Also gated by the `translations` feature flag (`feature:translations`).

## Gate
- Every route/action is protected by a permission (`can:` middleware). RED if
  any action lacks authz. Authorization lives on the route, not in a controller
  constructor (see `docs/base/conventions/coding.md` §Authorization).
