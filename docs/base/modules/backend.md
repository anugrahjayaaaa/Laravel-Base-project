# Packages (use existing libraries, don't reinvent)

Principle: if a maintained package covers the need, use it. Don't write custom
auth/RBAC/audit/log code. All packages below are verified on Packagist and
compatible with Laravel 13 + PHP 8.3.

## Core
| Need | Package | Version | Notes |
|------|---------|---------|-------|
| RBAC (roles + permissions) | `spatie/laravel-permission` | ^8.3 | Dynamic roles/permissions in DB; `permission:` middleware; `@can`. |
| Audit trail | `spatie/laravel-activitylog` | ^5.1 | Auto-log model events; `properties` delta; IP/UA captured. |
| API auth (mobile) | `laravel/sanctum` | (ships with Laravel) | Token guard for mobile; session guard for web. |
| Login throttling / lockout | Laravel built-in | — | `ThrottlesLogins` + `RateLimiter`; 5 fails → 15m lock. No extra package. |
| Password hashing | Laravel built-in | — | Argon2id default. No extra package. |
| Email verification | Laravel built-in | — | `MustVerifyEmail` contract + notification. No extra package. |
| Soft deletes | Laravel built-in | — | `SoftDeletes` trait. No extra package. |
| Health check | Laravel built-in | — | `laravel/health` (`./artisan health:setup`). No extra package. |

## UI / tooling
| Need | Package | Notes |
|------|---------|-------|
| RBAC management UI | **Custom thin Blade CRUD** | Built on spatie/laravel-permission. `sarker/laravel-role-permission-ui` only supports Laravel ≤11 — dropped. |
| Log viewer (web) | `rap2hpoutre/laravel-log-viewer` ^3.0 | Web UI for `storage/logs`; gate behind super-admin. `opcodesio/log-viewer` only supports ≤Laravel 12 — dropped. |
| Error tracking | `sentry/sentry-laravel` | Sentry SDK; init in bootstrap; tag `user_id`. |
| Dev debugging UI | `laravel/telescope` ^5.22 (require-dev) + `seanbarton/laravel-periscope` ^0.3 (require-dev) | Telescope collects telemetry (local-only PII safety); Periscope is a companion Blade UI on top of it adding date-range filter, sort, type filters, live watch. Both gated by `telescope.view` permission + `telescope` feature flag. `telescope:prune` runs daily. |
| Admin template | AdminLTE 4.9.1 (dist zip) | `public/vendor/adminlte/`, not a Composer dep. |

## Testing
| Need | Package | Notes |
|------|---------|-------|
| Tests | `pestphp/pest` | Feature + unit; ships with Laravel. |
| Type safety | `larastan/larastan` | PHPStan for Laravel. |
| Style | `laravel/pint` | Pint (already in CI). |

## Notes
- Install order: `composer require spatie/laravel-permission spatie/laravel-activitylog
  sentry/sentry-laravel rap2hpoutre/laravel-log-viewer`.
- Run each package's `vendor:publish` + `migrate` after require.
- Lockout/verify/soft-delete/health use Laravel native — DON'T add packages for these.
