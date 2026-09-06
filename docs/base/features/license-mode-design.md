---
id: BASE-011
name: License Mode Design
status: active
---

# License Mode Design System

## Overview

`license_mode` (global setting) allows the instance to operate in two modes:

1. **Global** (`license_mode = global`): One license for the entire instance.
   The active plan is stored globally in `settings.active_plan`.
2. **Per-User** (`license_mode = per_user`): Each user has their own license
   row in the `licenses` table. `PlanService` resolves the effective plan per user.

Per-user mode is **production-ready** — implemented and tested (see
`tests/Feature/ArchitectureAuditTest.php`).

## Architecture

### Global mode
```
User → Setting::get('active_plan') → Plan
```

### Per-user mode
```
User → Active License (status=active, not expired) → License.plan_slug → Plan
```

Missing license → falls back to `Setting::get('default_plan', 'free')`.

**Super-admin bypass**: `Gate::before` returns `null` for super-admin
(`User::isSuperAdmin()`), deferring to the policy. Super-admins bypass Plan
entitlement checks but are NOT exempt from Pennant feature flags.

## Schema

```sql
-- Global setting stored in `settings` table
INSERT INTO settings (key, value) VALUES ('license_mode', 'global');
INSERT INTO settings (key, value) VALUES ('default_plan', 'free');

-- Per-user licenses table
CREATE TABLE licenses (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  plan_slug VARCHAR(50) NOT NULL DEFAULT 'free',
  license_key VARCHAR(255) UNIQUE,
  type VARCHAR(20) NOT NULL DEFAULT 'manual',
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  expires_at DATETIME NULL,
  issued_to VARCHAR(255) NULL,
  snapshot JSON NULL,
  created_at TIMESTAMP NULL,
  updated_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## Active License Criteria

A license is considered active and valid when:
- `status = 'active'`
- `expires_at IS NULL` OR `expires_at > now`

The `User::license()` hasOne relationship enforces this:
```php
return $this->hasOne(License::class)
    ->where('status', 'active')
    ->where(function ($q) {
        $q->whereNull('expires_at')
          ->orWhere('expires_at', '>', now());
    })
    ->latest('id');
```

## Models

### App\Models\User

```php
// Historical relationship (hasMany — preserves history)
public function licenses(): HasMany
{
    return $this->hasMany(License::class);
}

// Current active license (hasOne — scoped to active + non-expired)
public function license(): HasOne
{
    return $this->hasOne(License::class)
        ->where('status', 'active')
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->latest('id');
}
```

### App\Models\License

Fields: `id`, `user_id`, `plan_slug`, `license_key`, `type`, `status`,
`expires_at`, `issued_to`, `snapshot`, `revoke_reason`

Scopes: `scopeActive()`, `isActiveAndValid()`

## Enums

### App\Enums\LicenseMode

```php
enum LicenseMode: string {
    case GLOBAL = 'global';
    case PER_USER = 'per_user';
}
```

## Services

### PlanService

Single entry point for resolving a user's effective `Plan`.

```php
// Auto-detects User passed as first arg — all production callers use this form:
PlanService::for($user)

// Explicit two-arg form (used in tests):
PlanService::for(null, $user)

// Global usage (no user context):
PlanService::for()
```

- **Global mode**: resolves `Setting::get('active_plan', 'free')`.
- **Per-user mode**: resolves `User::license()->first()->plan_slug`, with
  Free fallback via `Setting::get('default_plan', 'free')` when no active license.

### LicenseService

| Method | Purpose |
|--------|---------|
| `issue(string $planSlug, array $attrs)` | Issue a license. Idempotent — reactivates existing. |
| `issueFor(User $user, string $planSlug, array $attrs)` | Issue a per-user license (sets `user_id`). |
| `defaultLicenseForUser(User $user)` | Issue a Free license for a new user (per-user provisioning). |
| `activate(string $key, ?string $issuedTo, ?User $forUser)` | Activate a license. Global mode: sets global settings + revokes other licenses. Per-user: updates only the target user's license. |
| `verify(string $key, License $license)` | Verify key signature. Includes `user_id` in payload for per-user keys. |
| `status(?User $user)` | Current license status string. |
| `daysLeft(?User $user)` | Days until expiration (null = lifetime). |

**License key format**: `LIC-{PLAN}-{hash}` where hash is derived from
`slug|expires_at|u{userId}` for per-user licenses. Global licenses omit the
`|u{userId}` segment — backward compatible with the old key format.
`LicenseService::verify()` uses the same derivation, including `user_id` when
checking per-user licenses.

### UserService

`UserService::create(array $data)` provisions a Free license for new users when
`license_mode = 'per_user'`.

## Admin UI

Dropdown in `/settings/system`:
```
[Global (Instance)]     → license_mode = global
[Per-User]             → license_mode = per_user
```

Form Request validation:
```php
'license_mode' => ['required', Rule::enum(LicenseMode::class)]
```

## Dashboard

In per-user mode, the dashboard shows:
- Current plan (from user's license)
- License status and days remaining
- Expiration warning when `expires_at <= now() + 7 days` and `expires_at > now`
- Super-admin users are excluded from the warning

## Tests

- Global mode returns `settings.active_plan`
- Per-user mode returns user's license plan
- Fallback to `default_plan` if no license
- License key uniqueness across users with same plan + expiry
- License verification with user-specific keys
- Per-user activation isolation (one user's activation does not affect others)
- Global activation regression (existing behavior unchanged)
- Dashboard production path resolves correct per-user plan via `PlanService::for($user)`
