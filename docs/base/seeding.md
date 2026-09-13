# Seeding

## Overview

The project ships with a domain-oriented seeder set. Each seeder owns ONE
reference-data domain; `DatabaseSeeder` only orchestrates execution order.
All seeders use `updateOrCreate`/`findOrCreate`/`syncPermissions` so they are
safe to rerun (idempotent).

## Structure

```text
database/seeders/
├── DatabaseSeeder.php    orchestration only — no logic
├── PermissionSeeder.php  spatie permissions (33, web guard)
├── RoleSeeder.php        roles + permission assignments
├── SettingSeeder.php     system settings defaults
├── PlanSeeder.php        Free (only deterministic reference plan)
├── AdminUserSeeder.php   first-run super-admin
└── LanguageLineSeeder.php spatie lang lines
```

## Execution order

```
PermissionSeeder → RoleSeeder → PlanSeeder → SettingSeeder → AdminUserSeeder → LanguageLineSeeder
```

Dependencies:
1. **PermissionSeeder** — permissions (referenced by RoleSeeder).
2. **RoleSeeder** — roles; `super-admin` + `admin` get all perms, `staff`
   gets none (staff entitlement resolves via plan features — see auth.md
   §Authorization). `syncPermissions` is idempotent.
3. **PlanSeeder** — plans referenced by SettingSeeder's `active_plan`/`default_plan`.
4. **SettingSeeder** — global settings (`active_plan`, `default_role`,
   `license_mode`). `license_secret` is deliberately NOT seeded (env-only).
5. **AdminUserSeeder** — seeds admin user + assigns `super-admin` role
   (depends on RoleSeeder).
6. **LanguageLineSeeder** — spatie translatable strings (last; no deps).

## What each owns

| Seeder            | Owns                              | Values                         | Source |
| ----------------- | --------------------------------- | ------------------------------ | ------ |
| PermissionSeeder  | spatie `permissions`              | 33 permissions (web guard)     | `docs/base/modules/backend.md` §RBAC |
| RoleSeeder        | spatie `roles` + assignment       | super-admin, admin, staff      | `docs/authorization.md` §Roles |
| SettingSeeder     | `settings` key/value              | active_plan=free, default_plan=free, default_role=staff, license_mode=global | `docs/base/features/licensing-and-billing.md` §46-49 |
| PlanSeeder.php        | `plans`                           | free, pro, enterprise (3 tiers) | `docs/base/features/plan-limits-design.md` §44 |
| AdminUserSeeder   | `users` first-run admin           | admin@laravel-base.local       | dev/first-run only |
| LanguageLineSeeder| spatie `language_lines`           | from `lang/{en,id}/`           | `docs/i18n.md` |

## Plans

All three tiers are seeded (`PlanSeeder`): free, pro, enterprise.

| slug | price | members | storage_mb | features | rbac |
| --- | --- | --- | --- | --- | --- |
| free | 0 | 2 | 500 | [] | allowed_permissions=[], no roles feature |
| pro | 99000 | 5 | 2000 | kanban, audit, telescope | role.create via roles feature |
| enterprise | 499000 | 0 (unlimited) | 0 (unlimited) | all 15 pennant flags | role.create via roles feature |

- `0` in numeric limits = unlimited (`PlanService::limit` returns int; callers like `membersLeft` use `max(0, limit - count)`).
- `features` are a subset of the 15 pennant flags in `config/pennant.php`;
  `PlanRequest` validates `features.*` against pennant.
- Pro baseline (99000/5/3/kanban+audit+telescope) is sourced from test
  fixtures (`tests/Feature/{BillingTest,LicensingTest,QaSmokeTest}`);
  Enterprise is a progressive derivation — see plan-limits-design.md §4.
- Free uses all-minimal/zero limits by explicit project decision.

## Admin user (security)

- **Development / first-run seed data only.**
- email `admin@laravel-base.local` reserved placeholder (not a real inbox).
- Password seeded via `Hash::make('#Password123')` (bcrypt) — never plaintext
  in repo.
- Phone masked `+628****0001` — no real PII.
- In production, replaced by env-driven provisioning.

## Idempotency

- `updateOrCreate` keyed on `slug` / `email` / `key` — reruns update, never
  duplicate.
- `Permission::findOrCreate` (spatie) — reruns are no-ops.
- `Role::syncPermissions` — replaces the permission set per run.
