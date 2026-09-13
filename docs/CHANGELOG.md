# Changelog: feature/general-fixes

All changes for use in Issue Tracker (fork Laravel-Base-Project).

## Platform-Level Superadmin Bypass (Super-Admin Architecture)

### New architecture
A platform-level `super-admin` user (username `superadmin`) is seeded during
initialization. This user represents a platform operator, not a commercial
subscriber, and must NOT be restricted by Plan entitlement.

### `app/Models/User.php`
- Added `isSuperAdmin(): bool` — returns true when the user holds the `super-admin` **Role**
  (via `$this->hasRole('super-admin')`). Does NOT check username.
  This is the single source of truth for super-admin identity.

### `app/Providers/AppServiceProvider.php` (Gate `before` callback)
- Added `BypassService::isSuperAdmin()` check at the top of the Gate `before` callback.
- When super-admin: returns `null` (defer to spatie normal Role check) — bypasses
  BOTH Plan permission boundary (`PlanService::allows()`) AND Plan feature boundary.
- When NOT super-admin: existing logic — Plan boundary checks remain, `role.*`/`permission.*`
  still exempt.
- **Pennant is NOT bypassed** — Pennant is checked at route middleware level
  (`EnsureFeatureEnabled`), separate from the Gate. Pennant OFF → 404 for everyone,
  including super-admin.

### `app/Services/BypassService.php` (NEW)
- Central bypass decision class. `isSuperAdmin()` delegates to `User::isSuperAdmin()`.
- Designed for extensibility (future: license-status bypass, plan-trial bypass).
- Keeps bypass logic OUT of the Gate closure — clean separation.

### Key design decisions
- **Super-admin identity = Role, not username**: The `super-admin` role is seeded with
  ALL permissions. `isSuperAdmin()` checks `hasRole('super-admin')`. Username `superadmin`
  is seed data only.
- **No `model_has_permissions` writes**: Super-admin's permissions come from the role.
- **No Plan → Role synchronization**: `syncPermissionsForPlan()` remains a no-op.
- **Pennant kill switch applies everywhere**: Even super-admin gets 404 when a feature
  is deactivated.
- **Privilege escalation prevention**: `super-admin` role name is reserved in
  `StoreRoleRequest`/`UpdateRoleRequest` — cannot be created via UI/API.
- **Other Plan limits (max_members, quotas, etc.)**: Not enforced in middleware/controllers.
  Report only — if added later, BypassService needs extension.

### Gate `before` decision table

| User type  | Pennant | Plan permission | Plan feature | Result         |
|------------|---------|-----------------|--------------|----------------|
| Normal     | ON      | Allowed         | Allowed      | ALLOW (gate)   |
| Normal     | ON      | Denied          | —            | 403            |
| Normal     | OFF     | any             | any          | 404            |
| Superadmin | ON      | any             | any          | ALLOW (bypass) |
| Superadmin | OFF     | any             | any          | 404            |

### §Permission Sync (from original audit)
- `syncPermissionsForPlan()` is a **no-op** — it does NOT copy Plan permissions to Users
  or Roles. Permissions are evaluated at runtime via Role ∩ Plan, not synced.
  See §Effective Permission below for the super-admin extension.

## License Mode Support

### `config/pennant.php`
- Added `features` flag to feature list (Features Management module)
- Without this, `@feature('features')` always fails → sidebar menu hidden even for super-admin

### `app/Enums/LicenseMode.php` (NEW)
- Enum: `global` | `per_user`
- Used in SettingsController to render dropdown

### `database/migrations/2025_01_01_000000_create_licenses_table.php` (DELETED)
- **Conflict**: Project already has `licenses` table migrations
- Do NOT create duplicate — use existing table which has: `id`, `plan_slug`, `license_key`, `type` (recurring|lifetime|manual), `status` (active|revoked|expired), `issued_to` (instance binding), `expires_at`, `snapshot` (JSON, versioned catalog), `user_id` (nullable, added later)

### `app/Models/License.php`
- Model already has `user_id` in fillable + `user()` relation
- Scopes: `active()` (status=active AND not expired), `isActiveAndValid()`
- Do NOT overwrite — revert if changed

### `app/Services/PlanService.php`
|- Added `?User $user = null` param to `for()` method
|- Per-user mode: resolves plan from `$user->license?->plan_slug`
|- Global mode: uses `settings.active_plan` or `settings.default_plan` (unchanged)
|- `syncPermissionsForPlan()` is a **no-op** — permissions are evaluated at runtime
  via Role ∩ Plan, not synced into Users. See §Permission Resolution.

### `app/Http/Controllers/SettingsController.php`
- Added `license_mode` field to validation + setting persistence
- Pass `$licenseMode` to view

### `resources/views/settings/system.blade.php`
- Added dropdown for license mode (global/per_user)
- Must be inside `<form>` that posts to `settings.system.update` route
- Validation: `'license_mode' => ['required', 'string', 'in:global,per_user']`

### `database/seeders/DatabaseSeeder.php`
- Added `Setting::updateOrCreate(['key' => 'license_mode'], ['value' => 'global'])`
|- Staff role: `syncPermissions([])` — no direct permission assignment
|- Permissions come from Role assignment + Plan runtime check, NOT plan sync
- Free plan seed: `features: []`, `limits: ['max_members' => 2, 'max_storage_mb' => 500, 'max_roles' => 3, 'max_permissions' => 0]`

## Plan Form & Validation

### `app/Http/Requests/Plan/PlanRequest.php`
- `is_active` rule: keep `'boolean'` (NOT nullable) — but form uses hidden input + checkbox value trick
- `features` rule: `'nullable|array'` — allows empty features array
- `max_permissions` rule: `'nullable|integer|min:0'`

### `resources/views/plans/form.blade.php`
- `is_active` checkbox: add hidden input `value=0` + checkbox `value=1`
  ```html
  <input type="hidden" name="is_active" value="0">
  <input type="checkbox" name="is_active" value="1" ...>
  ```
- Capacity section: wrap `max_members`, `max_storage_mb`, `max_roles` in `@if(license_mode === 'global')` — per-user mode hides these, keeps `max_permissions` + `max_features`
- Features section: counter badge only shown in global mode
- Permission checkboxes: add class `perm-toggle` for reliable JS selector
- JS IIFE: handle `max_features=0` → disable all feature checkboxes; `max_permissions=0` → disable permission checkboxes (global mode only)

### `app/Models/Plan.php`
- LIMIT_KEYS: exact final schema — `max_members`, `max_roles`, `max_permissions`, `max_features`, `max_storage_mb` + `allowed_permissions`
  - Removed: `max_projects` (never implemented), `can_create_roles` (replaced by `role.create` permission via `roles` feature)
  ```php
  'max_members', 'max_roles', 'max_permissions', 'max_features', 'max_storage_mb'
  ```

### `app/Http/Controllers/PlanController.php`
|- Calls `PlanService::syncPermissionsForPlan($plan)` after `store()` + `update()`
  (no-op — preserved as call site for future use; does NOT sync to Users)

## Permission Resolution (CHALLENGE 2 architecture)

`syncPermissionsForPlan(Plan $plan)` is a **no-op** — it does NOT copy Plan
permissions to Users or Roles. Permissions are resolved at runtime:

```
Effective Permission = User.Role.Permission AND Plan.allows(Permission)
```

- **Plan.allowed_permissions** = commercial entitlement boundary (what the Plan
  *permits*). Does NOT grant by itself. Plan changes do NOT modify
  `model_has_permissions`, `model_has_roles`, or `role_has_permissions`.
- **Role.permissions** = persistent assignment. Plan downgrade does NOT remove
  permissions from Roles. Permissions stored under a previous Pro plan remain.
- **Gate `before`** (`AppServiceProvider`): checks Plan.allows(perm) for all
  domain permissions; `role.*` / `permission.*` are exempt.
- **RoleController::filterPermissions** (web + API): server-side enforcement —
  a subscriber with role management capability can only assign permissions in
  the Plan's `allowed_permissions`.

## Sidebar & Navigation

### `resources/views/partials/layout/sidebar.blade.php`
- Features menu: wrapped in `@can('feature.manage')` + `@feature('features')`
- All nav items already gated by `@can` + `@feature` combination

## Auth Form Validation & Accessibility Fixes

### Error display pattern (semua auth form konsisten)
Pattern: `@if($errors->any())` alert div (general error) + `@error('field')` invalid-feedback (field-level)

### `resources/views/auth/login.blade.php`
- Hapus `@error('identifier')` block — LoginController already keys error to `identifier` (caused duplicate display)
- Password error: ganti `invalid-feedback d-block` → `invalid-feedback` tanpa `d-block` (di luar input-group)
- Tambah `id="identifier"` + `aria-describedby` + `aria-invalid` di semua input
- Tambah `sr-only` label untuk accessibility (WCAG 2.1)
- Error block pindah ke luar input-group (setelah icon) — mencegah layout shift

### `resources/views/auth/register.blade.php`
- Semua `@error` block pindah ke luar input-group (setelah icon) — mencegah icon pindah kebawah
- Password toggle icon: kembalikan `<i class="bi bi-eye" id="password-confirm-icon">` — sebelumnya hilang
- Konsistenkan `invalid-feedback d-block w-100` untuk server-side errors
- Tambah `id`, `aria-describedby`, `aria-invalid` di semua input
- Tambah `sr-only` label untuk semua field (name, username, email, phone, password, password_confirmation)

### `resources/views/auth/forgot-password.blade.php`
- Tambah `id="email"` + `aria-describedby` + `aria-invalid` di input
- Error: pindah ke luar input-group, pakai `invalid-feedback d-block w-100` + `role="alert"` + `aria-live="polite"`
- Tambah `sr-only` label

### `resources/views/auth/reset-password.blade.php`
- Tambah password visibility toggle — konsisten dengan login/register (UX consistency)
- Add `@push('scripts')` dengan JS toggle (reusable IIFE pattern sama register)
- Fix DOM: push script & form closing berada di luar form yang benar (sebelumnya nested broken)

## General UI/UX Consistency (All Views)

### Two-form-pattern standard (established):
- **Auth forms** (`auth/*`): `sr-only` label (placeholder-only UX) + aria attributes (WCAG 2.1)
- **Admin forms** (`access/*`, `plans/*`, `settings/*`): visible `<label class="form-label">` + per-field error

### Checkbox standard:
- All checkboxes (boolean): hidden input `value=0` + checkbox `value=1` (prevents null-on-unchecked validation failure)
  - Examples: `is_active` (plans/form), `registration_enabled` (settings/system)

### Error display standard:
- General errors: `@if($errors->any())` alert block
- Field errors: `@error('field') <div class="invalid-feedback d-block">` (d-block required for server-side errors)
- Error block always OUTSIDE `.input-group` (mencegah layout shift/icon displacement)
- All error: `role="alert"` + `aria-live="polite"`

### Submit button / footer standard:
- All forms: `<div class="card-footer d-flex justify-content-end gap-2">` + btn-light cancel + btn-primary save

### `resources/views/access/users/edit.blade.php`
- Tambah password visibility toggle (sama kayak auth/register) — konsisten
- Per-field error: `invalid-feedback d-block` + `aria-describedby`
- Add `id` attributes untuk toggle JS target

### `resources/views/access/roles/edit.blade.php`
- Tambah `permissions[]` field error display
- `invalid-feedback d-block` + aria attributes pada semua field

### `resources/views/access/permissions/edit.blade.php`
- `invalid-feedback d-block` + `aria-describedby` + `aria-invalid`
- Add `id` attributes

### `resources/views/plans/form.blade.php`
- Ganti submit button wrapper `<div class="mt-4">` → `card-footer d-flex justify-content-end gap-2` — konsisten
- Hapus h3 hardcode (layout sudah punya content header via $title variable)
- Per-field error display pada capacity/permission checkboxes

### `resources/views/settings/system.blade.php`
- Tambah hidden input pada `registration_enabled` checkbox

### `resources/views/settings/translations/edit.blade.php`
- Refactor ke card-footer pattern
- Per-field error display per locale + aria attributes

### `resources/views/access/permissions/create.blade.php`
- Ganti hardcode `<h3>New Permission</h3>` → `<h3>{{ ui('new_permission') }}</h3>` — i18n consistency

### `resources/views/access/roles/create.blade.php`
- Ganti hardcode `<h3>New Role</h3>` → `<h3>{{ ui('new_role') }}</h3>` — i18n consistency

### `resources/views/billing/` (index + invoice)
- Already consistent: card + table + card-footer pattern, all i18n labels
- Micro: admin stat card `{{ ui('plans') }}` = stat count label, acceptable

### `resources/views/admin/billing/index.blade.php`
- Already consistent: stat cards + breakdown tables, all i18n labels

### `resources/views/profile/show.blade.php`
- Ganti submit button `<button>` langsung → `card-footer d-flex justify-content-end`
- Semua `@error` → `invalid-feedback d-block` (server-side visibility)

### `resources/views/partials/layout/header.blade.php`
- Notification link: hardcode ke audit.index — VERIFIED OK (audit log adalah notification utama)

### `resources/views/monitoring/audit/index.blade.php`
- IP header hardcode → `{{ ui('ip') }}` — i18n consistency
- Modal detail IP label hardcode → `{{ ui('ip') }}`

### `resources/views/monitoring/logs/index.blade.php`
- Already consistent: form-select, table, all ui() labels (Stack trace = technical term)

### `resources/views/notifications/index.blade.php`
- "read" hardcode → `{{ ui('read') }}` — i18n
- Add `read` key to lang/en/ui.php + lang/id/ui.php

## Testing
|- All 140 tests pass (375 assertions)
|- Auth subset: 29/29 pass
|- User tests: 31 pass
|- Role+Permission tests: 21 pass
|- Plan tests: 7 pass
|- Profile+User tests: 37 pass
|- Key test files: LicensingTest.php, PlanTest.php, RbacTest.php, QaSmokeTest.php

## Component & Button UI Consistency (Final Pass)

### Button Style Standardization
- All create buttons: `btn btn-primary` + `bi-plus-lg me-1` (replaced btn-sm, bi-plus-circle, missing spacing)
- Files: plans/index, billing/index, access/*/index, settings/api-tokens/index
- Subscribe buttons: added `bi-bell` icon (billing/index, plans/index)

### Action Buttons Component
- `<x-action-buttons>`: edit(pencil)/delete(trash)/restore(arrow)/forceDelete(x-circle), tooltip, min-width:38px
- Standardized all delete/moderation action icons in tables → use component, not text buttons

### Card Footer Standard
- All form submit sections: `<div class="card-footer d-flex justify-content-end gap-2">`
- Modal footers: `btn-secondary` cancel LEFT + `btn-danger/primary` confirm RIGHT
- Files: plans/form, profile/show, settings/translations/edit, all edit forms

### Modal Consistency
- delete-modal, force-delete-modal, feature-toggle-modal, bulk-confirm modal
- Pattern: modal-header(title) + modal-body(text) + modal-footer(cancel kiri, confirm kanan)
- bulk-actions modal uses JS form.submit() (dynamic action), modal-detail uses inline form

## Recent Commits (views/ux consistency)

### `7306cf9` — Create button standardization
- plans/index: btn-sm → btn, add bi-plus-lg me-1
- settings/api-tokens/index: bi-plus-circle → bi-plus-lg me-1

### `2b5ef55` — Billing + logs i18n
- billing/index: subscribe button add bi-plus-lg icon
- monitoring/logs/index: "Stack trace" → ui('stack_trace')

### `345a210` — Profile DOM fix
- profile/show: fix nested card-footer DOM structure
- Add password toggle visibility (konsisten register/admin user edit)

### `fdf9cb3` — Plans UX
- plans/index: billing period fallback ucfirst()
- plans/index: action buttons text → icon
