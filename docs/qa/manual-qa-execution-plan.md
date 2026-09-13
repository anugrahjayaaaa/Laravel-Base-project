# Manual QA Execution Plan

Scope: manual verification of every user-facing function in this Laravel Base Project.
Do not modify code during execution. Record results in the QA Execution Tracker.
Plan/License is tested last, after the rest of the application is verified.

## 1. Application Overview

Stack: Laravel 13, PHP 8.3+, MySQL 8, AdminLTE 4 + Bootstrap 5.3 + Bootstrap Icons.
Theming via `public/vendor/app-theme.css` (`--lbp-*` tokens), dark default.
No npm/Vite build step; assets ship from `public/vendor/*`.
Auth: web sessions + Sanctum bearer API.
Authorization: route-level `can:` + `feature:` middleware (ADR-0010).
Feature flags: Laravel Pennant.
Audit: spatie/activitylog.
i18n dual-source: `lang/{en,id}/{ui,messages,validation}.php` + spatie `language_lines` runtime overrides.
Secrets: license/webhook secrets from env; app fails closed if missing.

## 2. Feature Inventory

| ID | Module | Functionality | Interface | Dependencies | Priority |
| -- | ------ | ------------- | --------- | ------------ | -------- |
| F-01 | Health | `/up` JSON status | Web | None | P0 |
| F-02 | Auth | Login, logout, throttle | Web | None | P0 |
| F-03 | Auth | Forgot/reset password | Web | `password_reset_tokens` | P0 |
| F-04 | Auth | Registration (toggleable) | Web | `registration_enabled` setting | P0 |
| F-05 | Auth | Email verification | Web | VerifyEmail notification | P1 |
| F-06 | Auth | API login/logout/me/password/verify | API | Sanctum | P0 |
| F-07 | Dashboard | `/dashboard` summary | Web | Auth | P0 |
| F-08 | Profile | View/update profile, password | Web | Auth | P0 |
| F-09 | Profile | API profile/sessions/tokens | API | Sanctum | P1 |
| F-10 | Users | List/create/edit/delete/restore/force-delete/lock/unlock/reset-password/bulk | Web | `feature:users`, RBAC `user.*` | P0 |
| F-11 | Users | API user CRUD + lock/unlock/reset | API | Sanctum + `user.*` | P1 |
| F-12 | Roles | List/create/edit/delete/restore/force-delete/bulk | Web | `feature:roles`, `role.*` | P0 |
| F-13 | Permissions | List/create/edit/delete/restore/force-delete/bulk | Web | `feature:permissions`, `permission.*` | P0 |
| F-14 | Sessions | List, logout others | Web | `feature:sessions`, `session.*` | P1 |
| F-15 | API tokens | List/create/destroy | Web | `feature:api-tokens`, `api-token.*` | P1 |
| F-16 | Audit | List/export | Web | `feature:audit`, `audit.view`; spatie activitylog | P1 |
| F-17 | Notifications | Index | Web | `feature:audit`, `audit.view` | P1 |
| F-18 | Logs | Log viewer page | Web | `feature:logs`, `logs.view` | P1 |
| F-19 | Feature flags | Toggle pennant flags | Web | `can:feature.manage` | P1 |
| F-20 | Settings | System settings + registration toggle | Web | `can:feature.manage` | P1 |
| F-21 | Translations | Edit runtime translations | Web | `feature:translations`, `translation.*` | P1 |
| F-22 | Locale | Switch locale | Web | Auth | P2 |
| F-23 | Plans | Plan CRUD | Web | `feature:plans`, `feature.manage` | P2 |
| F-24 | Billing | User billing portal + checkout/cancel/invoice | Web | `feature:billing` | P2 |
| F-25 | Billing | Admin billing dashboard | Web | `feature:billing`, `billing.view` | P2 |
| F-26 | Billing | Webhook endpoint | API | Public POST `/billing/webhook` | P1 |
| F-27 | i18n | UI text + validation + messages en/id | Both | Lang files + DB overrides | P1 |
| F-28 | RBAC | Route gates + middleware | Both | Spatie roles/permissions | P0 |
| F-29 | Navigation/sidebar | Sidebar links, protected visibility | Web | Feature flags + RBAC | P1 |
| F-30 | Error pages | 403/404 rendered views | Web | Auth middleware | P1 |

Non-UI but code-visible middleware/components:
- `SetLocale`, `SetApiLocale`
- `SecurityHeaders`, `LogHttpErrors`
- `PeriscopeAuthorize`
- `EnsureFeatureEnabled`
- `RegistrationEnabled`

## 3. Recommended Execution Order

1. App availability and environment sanity (`/up`, install/run).
2. Authentication/session (login/logout/password reset/registration).
3. Profile + locale.
4. Navigation/sidebar visibility.
5. RBAC foundation (roles/permissions).
6. Users.
7. Sessions/API tokens.
8. Audit/notifications/logs.
9. Settings/translations.
10. Feature flags.
11. Dashboard.
12. API v1.
13. Plans/License/Billing last.
14. Cross-feature workflows.
15. Regression + edge cases.

Dependency rationale: later modules gate on auth, roles, permissions, or flags. Test dependencies before dependents.

## 4. Detailed Test Cases

### Phase A — Bootstrap
- QA-001: Open `/up` → expect JSON `{"status":"ok"}` with 200.
- QA-002: Visit `/` unauthenticated → expect redirect/login view.
- QA-003: Confirm `.env` essentials present (`APP_KEY`, DB, Sanctum/Pennant installed).
- QA-004: Run `php artisan test` once before manual QA; note unrelated failures.

### Phase B — Authentication
- QA-005: Login with valid admin credentials → expect redirect to `/dashboard`.
- QA-006: Login with invalid password → expect validation error, no session.
- QA-007: Login throttle after repeated invalid attempts → expect 429/throttle response.
- QA-008: Logout → expect redirect to login, session cleared.
- QA-009: Access protected route while logged out → expect redirect to login.
- QA-010: Email verification flow if enabled → verify link behavior.
- QA-011: Forgot password → submit known email → expect reset link/sent feedback.
- QA-012: Reset password with valid token → expect password updated, login possible.
- QA-013: Reset password with invalid/expired token → expect error.
- QA-014: Registration when enabled → create account → expect redirect/login.
- QA-015: Registration when disabled → `/register` → expect 404.

### Phase C — Profile and Locale
- QA-016: Open `/profile` → expect current values prefilled.
- QA-017: Update name/email with valid data → expect success message and persisted values.
- QA-018: Update with invalid email format → expect validation error, no change.
- QA-019: Change password with correct current password → expect success.
- QA-020: Change password with wrong current password → expect validation error.
- QA-021: Switch locale (id/en) → expect UI terminology changes.
- QA-022: API `GET /v1/profile` with bearer token → expect own profile JSON.
- QA-023: API `PUT /v1/profile` → expect update and 200.

### Phase D — Navigation/Sidebar
- QA-024: Sidebar shows dashboard and modules user can access.
- QA-025: Sidebar hides modules when feature flag is OFF.
- QA-026: Sidebar hides modules when user lacks permission.
- QA-027: Non-existent route `/x` → expect 404 with project error view.

### Phase E — RBAC: Roles and Permissions
- QA-028: Create role with valid name → expect persisted in list.
- QA-029: Create role with duplicate name → expect validation error.
- QA-030: Assign permissions to role → expect saved and reflected.
- QA-031: Delete role in use by users → expect blocked or cascade per implementation.
- QA-032: Restore soft-deleted role → expect visible again.
- QA-033: Force delete role → expect permanent removal.
- QA-034: Bulk delete roles → expect selected removed.
- QA-035: Direct URL access without permission → expect 403.
- QA-036: Direct URL with permission but feature OFF → expect 404 from `EnsureFeatureEnabled`.

### Phase F — Permissions Module
- QA-037: Create permission with valid guard/name → expect success.
- QA-038: Create duplicate permission name+guard → expect validation error.
- QA-039: Delete permission assigned to roles → expect blocked/handled.
- QA-040: Restore and force delete permission → expect same user-visible behavior as roles.

### Phase G — Users
- QA-041: Create user with valid data and role assignment → expect list shows user.
- QA-042: Create user with invalid email/weak password → expect validation error.
- QA-043: Create user with existing email → expect validation error.
- QA-044: Edit user role/permissions → expect effective access changes.
- QA-045: Soft delete user → expect not active in default list.
- QA-046: Restore user → expect visible again.
- QA-047: Force delete user → expect removed permanently.
- QA-048: Bulk delete/restore → expect batch behavior.
- QA-049: Lock user → expect unable to login.
- QA-050: Unlock user → expect login restored.
- QA-051: Send reset password email → expect success; password reset flow works.
- QA-052: Unauthorized user opens `/users` → expect 403.
- QA-053: Authorized user with feature flag off → expect 404.

### Phase H — Sessions and API Tokens
- QA-054: Sessions list shows current device/session.
- QA-055: Logout others invalidates other sessions, current remains.
- QA-056: Create API token → expect visible in list.
- QA-057: Delete API token → expect removed.
- QA-058: Use deleted token against API → expect unauthorized.
- QA-059: API token scopes/abilities if implemented → expect enforced.

### Phase I — Audit, Notifications, Logs
- QA-060: Perform an action (e.g., edit user) → expect activity record in audit.
- QA-061: Audit export returns readable file/response.
- QA-062: Notifications index shows notification records if any.
- QA-063: Mark all read / unread count if implemented → expect count updates.
- QA-064: Logs page renders when feature ON and authorized.
- QA-065: Logs page 403/404 when unauthorized or feature OFF.

### Phase J — Settings and Translations
- QA-066: Open settings → expect registration toggle and locale defaults.
- QA-067: Toggle registration → expect registration route visibility flips.
- QA-068: Update translation line → expect UI reflects change immediately or after reload.
- QA-069: Add missing translation key if supported → expect behavior.
- QA-070: Direct `/settings` without `feature.manage` → expect 403.

### Phase K — Feature Flags
- QA-071: Toggle a pennant flag ON/OFF → expect navigation presence flips.
- QA-072: Toggle OFF for protected route → expect 404.
- QA-073: Toggle ON but user lacks permission → expect 403.
- QA-074: API flag behavior mirrors web when gated.

### Phase L — Dashboard
- QA-075: Open dashboard as admin → expect widgets/summary load.
- QA-076: Open dashboard as non-admin → expect limited or default view.
- QA-077: Dashboard after disabling feature flags does not crash.

### Phase M — API v1
- QA-078: `POST /v1/login` valid → expect bearer token.
- QA-079: `POST /v1/login` invalid → expect 401.
- QA-080: `GET /v1/me` with token → expect authenticated user payload.
- QA-081: `GET /v1/users` without `user.view` → expect 403.
- QA-082: `GET /v1/users` with feature OFF → expect 404.
- QA-083: API CRUD for roles/permissions → expect same auth/feature gating.
- QA-084: API token CRUD → expect owned-token visibility and delete behavior.
- QA-085: API password change → expect success/failure per validation.
- QA-086: API email verify/resend → expect success/failure behavior.

### Phase N — Cross-Feature Workflows
- QA-098: Create role → attach permissions → create user with role → login as user → verify access.
- QA-099: Disable feature flag → verify UI route + direct URL → re-enable → verify restored.
- QA-100: Change registration setting → verify public registration visibility before/after.
- QA-101: Edit translation → verify across two pages using same key.
- QA-102: Create API token → call API → delete token → call API again → verify 401.
- QA-103: Create user → soft delete → restore → force delete → verify state transitions.
- QA-104: Lock user → attempt login → unlock → login succeeds.
- QA-105: Change password via profile → login with new password → old password fails.

### Phase O — Plan/License/Billing
- QA-106: Plan CRUD list/create/edit/delete under `/plans`.
- QA-107: Assign plan to user → expect plan list/detail reflects assignment.
- QA-108: License activation flow → expect license state persisted.
- QA-109: License expiration/revocation → expect UI updates/restrictions.
- QA-110: Dummy billing checkout → expect invoice viewable and billing state.
- QA-111: Billing cancel → expect state update.
- QA-112: Admin billing page loads with expected KPIs.
- QA-113: Webhook with invalid/missing secret → expect fail closed.
- QA-114: Verify user cannot access another user’s license/billing data.

KNOWN ISSUE — DO NOT BLOCK GENERAL QA: Plan/License has known issues; record observed behavior and continue with other modules.

## 5. Authorization Matrix

| Feature | Superadmin / `feature.manage` | Role with permission | Role without permission | Authenticated without specific permission | Guest |
| -- | -- | -- | -- | -- | -- |
| Users | Visible + full CRUD | Visible + scoped actions | Hidden/403 | Hidden/403 | Redirect |
| Roles | Visible + full CRUD | Visible + scoped actions | Hidden/403 | Hidden/403 | Redirect |
| Permissions | Visible + full CRUD | Visible + scoped actions | Hidden/403 | Hidden/403 | Redirect |
| Sessions | Visible | Visible if `session.view` | 403 | 403 | Redirect |
| API tokens | Visible + create/delete | Visible/create/delete | 403 | 403 | Redirect |
| Audit | Visible | Visible if `audit.view` | 403 | 403 | Redirect |
| Notifications | Visible | Visible if `audit.view` | 403 | 403 | Redirect |
| Logs | Visible | Visible if `logs.view` | 403 | 403 | Redirect |
| Feature flags | Manage | Manage if `feature.manage` | 403 | 403 | Redirect |
| Settings | Manage | Manage if `feature.manage` | 403 | 403 | Redirect |
| Translations | Manage | View/edit per permissions | 403 | 403 | Redirect |
| Plans | Manage | Manage if `feature.manage` | 403 | 403 | Redirect |
| Billing user portal | Visible | Visible if `feature:billing` | 403/hidden | 403/hidden | Redirect |
| Billing admin | Visible | Visible if `billing.view` | 403 | 403 | Redirect |

For every row, test both UI access and direct URL/API request.

## 6. Feature Flag Matrix

Flags to verify: `users`, `roles`, `permissions`, `sessions`, `api-tokens`, `audit`, `logs`, `translations`, `plans`, `billing`.

For each flag:
- Flag ON + authorized → visible, routes respond.
- Flag ON + unauthorized → 403 on direct request.
- Flag OFF + authorized → hidden in UI, direct route 404.
- Flag OFF + unauthorized → 404.
- Sidebar item removed when OFF.
- API mirrors route behavior.

## 7. API Test Matrix

| Endpoint | Auth | Feature | Permission | 200 expected | 403 expected | 404 expected |
| -- | -- | -- | -- | -- | -- | -- |
| `POST /v1/login` | None | - | - | Valid | - | - |
| `POST /v1/forgot-password` | None | - | - | Valid | - | - |
| `POST /v1/reset-password` | None | - | - | Valid | - | - |
| `GET /v1/me` | Token | - | - | Yes | Invalid/missing token | - |
| `POST /v1/logout` | Token | - | - | Yes | Missing token | - |
| `PUT /v1/profile` | Token | - | - | Yes | Missing token | - |
| `GET /v1/sessions` | Token | `sessions` | `session.view` | Yes | No perm | No flag |
| `GET /v1/api-tokens` | Token | `api-tokens` | `api-token.view` | Yes | No perm | No flag |
| `POST /v1/api-tokens` | Token | `api-tokens` | `api-token.create` | Yes | No perm | No flag |
| `DELETE /v1/api-tokens/{id}` | Token | `api-tokens` | `api-token.create` | Yes | No perm | No flag |
| `GET /v1/audit` | Token | `audit` | `audit.view` | Yes | No perm | No flag |
| `GET /v1/features` | Token | `features` | `feature.manage` | Yes | No perm | No flag |
| `POST /v1/features/{slug}/toggle` | Token | `features` | `feature.manage` | Yes | No perm | No flag |
| `GET /v1/users` | Token | `users` | `user.view` | Yes | No perm | No flag |
| CRUD `v1/roles` | Token | `roles` | `role.view` | Yes | No perm | No flag |
| CRUD `v1/permissions` | Token | `permissions` | `permission.view` | Yes | No perm | No flag |

Also test invalid IDs, missing params, malformed JSON, empty bodies.

## 8. Cross-Feature Workflows

- QA-098: Create role → attach permissions → create user with role → login as user → verify access.
- QA-099: Disable feature flag → verify UI route + direct URL → re-enable → verify restored.
- QA-100: Change registration setting → verify public registration visibility before/after.
- QA-101: Edit translation → verify across two pages using same key.
- QA-102: Create API token → call API → delete token → call API again → verify 401.
- QA-103: Create user → soft delete → restore → force delete → verify state transitions.
- QA-104: Lock user → attempt login → unlock → login succeeds.
- QA-105: Change password via profile → login with new password → old password fails.

## 9. Plan/License Test Phase

Run only after Phases A–L pass.

- QA-106: Plan CRUD list/create/edit/delete under `/plans`.
- QA-107: Assign plan to user → verify effective entitlements.
- QA-108: License activation flow → expect state persisted.
- QA-109: License expiration/revocation → expect UI updates/restrictions.
- QA-110: Dummy billing checkout → expect invoice and billing state.
- QA-111: Cancel billing → expect state update.
- QA-112: Admin billing page loads with expected KPIs.
- QA-113: Webhook with invalid/missing secret → expect fail closed.
- QA-114: Verify user cannot access another user’s license/billing data.

KNOWN ISSUE — DO NOT BLOCK GENERAL QA: Plan/License has known issues; record observed behavior, continue if non-blocking.

## 10. Final Regression Checklist

- [ ] Login/logout across browsers/incognito.
- [ ] Permission change immediately affects protected page access.
- [ ] Feature toggle immediately affects navigation and route access.
- [ ] Registration toggle affects public registration.
- [ ] Locale persists across login/logout if intended.
- [ ] Activity log captures admin actions.
- [ ] API token revocation is immediate.
- [ ] Error pages render correctly for 403/404.
- [ ] Database transactions complete or rollback on failure for multi-step actions.
- [ ] No cross-user data leakage in API or web.

## 11. Known Issues / Existing Risks

- Plan/License implementation has known issues; treated as late-phase P2 until general QA complete.
- Stale docs vs code: verify documented behavior against current controllers/routes.
- i18n fallback mismatch risk: any `?? '...'` English fallback in Blade causes silent fallback bug; mark occurrences during QA.
- Test coverage != manual verification; Pest passing does not replace this manual pass.

## 12. QA Execution Tracker

Sequential checklist:

[ ] QA-001 `/up` JSON 200
[ ] QA-002 unauthenticated `/` behavior
[ ] QA-003 env/setup sanity
[ ] QA-004 baseline `php artisan test`
[ ] QA-005 login success
[ ] QA-006 login invalid
[ ] QA-007 throttle behavior
[ ] QA-008 logout
[ ] QA-009 protected redirect
[ ] QA-010 email verification
[ ] QA-011 forgot password
[ ] QA-012 reset password valid
[ ] QA-013 reset password invalid
[ ] QA-014 registration enabled
[ ] QA-015 registration disabled 404
[ ] QA-016 profile prefilled
[ ] QA-017 profile update
[ ] QA-018 profile invalid input
[ ] QA-019 password change valid
[ ] QA-020 password change invalid
[ ] QA-021 locale switch
[ ] QA-022 API profile read
[ ] QA-023 API profile update
[ ] QA-024 sidebar visibility
[ ] QA-025 sidebar flag-off behavior
[ ] QA-026 sidebar permission behavior
[ ] QA-027 404 page
[ ] QA-028 create role
[ ] QA-029 duplicate role
[ ] QA-030 assign permissions
[ ] QA-031 delete in-use role
[ ] QA-032 restore role
[ ] QA-033 force delete role
[ ] QA-034 bulk delete roles
[ ] QA-035 direct URL no perm
[ ] QA-036 direct URL flag off
[ ] QA-037 create permission
[ ] QA-038 duplicate permission
[ ] QA-039 delete assigned permission
[ ] QA-040 restore/force delete permission
[ ] QA-041 create user
[ ] QA-042 invalid user fields
[ ] QA-043 duplicate email
[ ] QA-044 edit user role/permissions
[ ] QA-045 soft delete user
[ ] QA-046 restore user
[ ] QA-047 force delete user
[ ] QA-048 bulk delete/restore users
[ ] QA-049 lock user
[ ] QA-050 unlock user
[ ] QA-051 reset user password
[ ] QA-052 users page unauthorized
[ ] QA-053 users page feature off
[ ] QA-054 sessions list
[ ] QA-055 logout others
[ ] QA-056 create API token
[ ] QA-057 delete API token
[ ] QA-058 deleted token rejects API
[ ] QA-059 token scopes if any
[ ] QA-060 audit record created
[ ] QA-061 audit export
[ ] QA-062 notifications list
[ ] QA-063 notification read/unread
[ ] QA-064 logs page authorized
[ ] QA-065 logs page unauthorized/off
[ ] QA-066 settings page
[ ] QA-067 registration toggle
[ ] QA-068 translation edit
[ ] QA-069 translation add
[ ] QA-070 settings direct 403
[ ] QA-071 toggle flag navigation
[ ] QA-072 toggle flag route 404
[ ] QA-073 toggle flag 403
[ ] QA-074 API flag behavior
[ ] QA-075 dashboard admin
[ ] QA-076 dashboard limited
[ ] QA-077 dashboard feature-off safety
[ ] QA-078 API login valid
[ ] QA-079 API login invalid
[ ] QA-080 API me
[ ] QA-081 API users no perm
[ ] QA-082 API users flag off
[ ] QA-083 API roles/permissions gating
[ ] QA-084 API token CRUD
[ ] QA-085 API password change
[ ] QA-086 API email verify/resend
[ ] QA-098 RBAC end-to-end
[ ] QA-099 flag toggle + access
[ ] QA-100 registration setting
[ ] QA-101 translation cross-page
[ ] QA-102 API token revocation immediate
[ ] QA-103 user delete/restore/force-delete
[ ] QA-104 lock/unlock login
[ ] QA-105 password change login
[ ] QA-106 plan CRUD
[ ] QA-107 user plan assignment
[ ] QA-108 license activation
[ ] QA-109 license expiration
[ ] QA-110 dummy billing checkout
[ ] QA-111 billing cancel
[ ] QA-112 admin billing KPIs
[ ] QA-113 webhook fail-closed
[ ] QA-114 cross-user license isolation
[ ] QA-115 regression checklist
[ ] QA-116 edge case pass
[ ] QA-117 final sign-off
