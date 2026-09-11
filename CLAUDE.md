# Laravel Base Project — AI Agent Guide

Reusable Laravel 13 admin base: auth, RBAC, audit, feature flags, licensing/billing.
This file is the **entry point for AI agents**. Human devs start at `docs/README.md`.

## Before any task
1. Read `docs/README.md` (single source of truth) + the relevant `docs/*.md`.
2. If code conflicts with docs → **docs win**; change via ADR (`docs/adr.md`).
3. Follow the dev-lifecycle skill if available; otherwise: branch → code → test → PR.

## Stack (current)
- PHP 8.3+, Laravel 13, MySQL 8.
- AdminLTE 4 + Bootstrap 5.3 + Bootstrap Icons, themed via `public/vendor/app-theme.css`
  (tokens `--lbp-*`), dark default. **No npm/Vite build step** — all CSS/JS ships from
  `public/vendor/*` (committed). Do NOT add a build pipeline or `package.json`.
- Pest for tests; spatie (permission, activitylog), sanctum, pennant.

## Hard rules (AI must obey)
- **Never merge to `main`.** Work on `feature/<name>` branch, push, open PR.
- Run tests before declaring done: `php artisan test` (or a filtered slice).
- Authorization: gate on the **route** (`can:` + `feature:` middleware), never in a
  controller `__construct()`. See `docs/coding-standard.md` + `docs/PRD.md` §5.
- Validation: dedicated Form Request, never inline `$request->validate()` in controllers.
- i18n: `lang/{en,id}/{ui,messages,validation}.php` are source of truth; spatie `language_lines`
  override at runtime. `ui()` = ui.php (UI terminology), `__('messages.*')` = messages.php
  (feedback). Do NOT create new translation namespaces — see `docs/base/features/i18n.md`.
  Never hardcode English in Blade. Locale parity enforced by `tests/Feature/TranslationTest.php`.
- Secrets: never commit; license/webhook secrets come from env and fail closed if missing.
- Docs match code. Update `docs/` + ADR when behaviour changes.

## Scope boundaries
- v1 is single-tenant (schema ready for `tenant_id`). Out of scope: MFA/2FA, SMS OTP,
  multi-tenant, file-upload module.
- The Issue-Tracker / Helm repos are SEPARATE — do not confuse this base project with them.
