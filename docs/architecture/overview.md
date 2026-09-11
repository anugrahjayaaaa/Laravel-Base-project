# Architecture

## Stack (locked)
| Layer | Tech |
|-------|------|
| Language | PHP 8.3+ |
| Framework | Laravel 13 (latest) |
| DB | MySQL 8 (InnoDB, utf8mb4_0900_ai_ci) |
| Frontend | Bootstrap 5.3 + AdminLTE 4.9.1 (Blade) |
| Auth | Laravel built-in + spatie/laravel-permission |
| Audit | spatie/laravel-activitylog |
| API auth | Laravel Sanctum |
| Observability | Laravel Log + Sentry + /up |
| Tests | Pest |

## Layered architecture
```
HTTP (web Blade / API JSON)
  → Controller (thin: validate + dispatch)
    → Service / Action (business logic)
      → Eloquent Models (SoftDeletes)
        → MySQL
- Custom models: `App\Models\User`, `App\Models\Role`, `App\Models\Permission` all use `SoftDeletes`;
  `config/permission.php` maps spatie to the custom Role/Permission.
Cross-cutting: auth, rbac(`can:` middleware), feature(`feature:` flag gate), force-json, log-activity middleware;
Exceptions → Sentry + structured log. **Authorization is wired on routes, not controllers —
see `docs/base/conventions/coding.md` §Authorization and `docs/architecture/decisions/index.md` ADR-0010.**
```

---

## Implementation Reference (functions / models)

### Custom models `App\Models\Role` & `App\Models\Permission`
- Extend spatie's `Role`/`Permission` and add `SoftDeletes` (trash/restore/force-delete).
- **Why custom (not spatie base):** `config/permission.php` (`models.role` / `models.permission`)
  points here, so every RBAC query/mutation routes through these classes — required so the
  `RoleObserver` / `PermissionObserver` fire (spatie base would skip them).
- State: rows soft-deleted get `deleted_at` set; `forceDeleted()` clears the row permanently
  and is logged to **DB table `activity_log`** (see audit-trail.md).
- Observers registered in `AppServiceProvider` (not spatie's base provider).


## v1 modules
1. Auth (login username|phone, logout, register, reset pwd, failed-login lockout, email verify, profile self-service, session mgmt)
2. RBAC (roles, permissions, assignment UI — dynamic, gated by `feature:roles`/`feature:permissions` flags)
3. Audit Trail (read-only, filter by user/action/date)
4. User Management (CRUD, soft delete)
5. Dashboard home (+ license status badge)
6. API (Sanctum /api/v1)
7. Feature flags (Laravel Pennant, `/features` UI)
8. Plans (custom CRUD: slug, price, limits JSON, features array; `free` seeded)
9. Licensing + Billing (Model 1 per-instance license; dummy checkout + real PG webhook; billing portal + admin analytics — see `licensing-and-billing.md`)
10. Template reference (sidebar section from zip demo)

## Required v1 features
- Forgot/reset password (hashed token, 60m expiry, single-use)
- Failed-login lockout (5 fails → 15m lock) + auth endpoint rate limit
- Email verification (`email_verified_at`)
- User self-service: profile, avatar, change password, change phone
- Session management: list sessions, logout other devices
- Seed super-admin + first-run setup
- Dashboard home
- Health `/up` + security headers (CSP, HSTS, X-Frame-Options)
