---
id: BASE-001
name: Laravel Base System
type: base
status: implemented
---

# Laravel Base Project — System Scope & PRD

A reusable Laravel 13 admin base: auth, RBAC, audit, feature-flagged modules,
and (v1.x) licensing + billing scaffolding. Built so new modules drop in by
following one convention — no per-module bespoke wiring.

Implementation standards live in [coding.md](conventions/coding.md) (read
before writing code).

## 1. Purpose

> Status: **implemented** (current system).

Reusable foundation so derived projects get: authenticated admin UI, dynamic
RBAC, audit trail, feature flags, Sanctum mobile API, and a licensing/billing
seam — without re-building auth or authorization each time.

## 2. Users & roles

| Role | Access |
|------|--------|
|- super-admin | holds ALL permissions via the `super-admin` role (seeded with full permission set); is a platform-level override — bypasses Plan feature + Plan permission entitlement via `BypassService::isSuperAdmin()` (role-based check, not username-based). Pennant kill switch STILL applies (Pennant OFF → 404 for everyone, including super-admin). `role.*` / `permission.*` permissions are exempt from Plan checks (see §Enforcement). |
| admin | manages users/roles/permissions within granted permissions |
| staff | operational access (translations, logs), gated by permissions |
| end user | self-service: own profile, sessions, api-tokens |

## 3. v1 modules (implemented)

| # | Module | Key doc |
|---|--------|---------|
| 1 | Auth | [features/auth.md](./features/auth.md) |
| 2 | RBAC (roles/permissions) | [features/rbac.md](./features/rbac.md) |
| 3 | Audit Trail | [features/audit-trail.md](./features/audit-trail.md) |
| 4 | User Management | [features/auth.md] |
| 5 | Dashboard | [architecture/overview.md](../architecture/overview.md) |
| 6 | API (Sanctum) | [modules/api.md](modules/api.md) |
| 7 | Feature flags | [features/feature-flags.md](./features/feature-flags.md) |
| 8 | Plans | [features/licensing-and-billing.md](features/licensing-and-billing.md) §11 |
| 9 | Licensing + Billing | [features/licensing-and-billing.md](./features/licensing-and-billing.md) |
| 10 | Templates | [modules/frontend.md](modules/frontend.md) §Sidebar |

## 4. Non-functional requirements

- **Stack (locked):** PHP 8.3+, Laravel 13, MySQL 8 (utf8mb4_0900_ai_ci),
  Bootstrap 5.3 + AdminLTE 4.9.1 (Blade), Pest. See
  [architecture/overview.md](../architecture/overview.md).
- **Security:** every route/action gated by permission + feature flag;
  CSP/HSTS/X-Frame-Options; no secrets in repo.
- **Consistency:** one authorization pattern — gates on the route, never the
  controller constructor. See [ADR-0010](../architecture/decisions/README.md#adr-0010).

## 5. Authorization model (mandatory)

Access requires all of:
1. Authenticated session (`auth` middleware)
2. Permission gate (`can:{perm}` on the route)
3. Module enabled (`feature:{slug}` on the route)

Gate lives on the **route**, never controller `__construct()`. See
[conventions/coding.md](conventions/coding.md).

## 6. Out of scope (v1)

MFA/2FA, SMS OTP, in-app notifications, file-upload module, multi-tenant.
