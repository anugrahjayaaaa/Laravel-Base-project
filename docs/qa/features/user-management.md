# Feature 02 — User Management

## 1. Scope

Included:
- Admin user creation
- Admin user edit
- Username/email/phone uniqueness
- Password generation/hashing
- Email verification interaction (admin-created vs self-service)
- User role assignment and sync
- User lock/unlock (temporary + permanent)
- Soft delete / restore / force delete
- Bulk delete/restore/force-delete
- Admin-triggered password reset
- Login behavior after delete/restore
- Authorization boundaries for user actions
- Suspended/locked user visibility on user list

Explicitly out of scope:
- Plan limits / license behavior affecting users
- Billing dashboard visibility
- Notification generation specifics (covered in FEATURE 11)

## 2. Related QA Cases

- QA-041 create user
- QA-042 invalid user fields
- QA-043 duplicate email
- QA-044 edit user role/permissions
- QA-045 soft delete user
- QA-046 restore user
- QA-047 force delete user
- QA-048 bulk delete/restore users
- QA-049 lock user
- QA-050 unlock user
- QA-051 reset user password
- QA-052 users page unauthorized
- QA-053 users page feature off
- QA-103 user delete/restore/force-delete
- QA-104 lock/unlock login
- QA-105 password change login

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Admin create: requires `name`, `username`, `email`, `password`, optional `roles`. Phone is optional.
- Admin update: can change name, username, email, phone, password (optional), roles.
- Uniqueness is enforced in FormRequest (`unique:users,username/email/phone`). DB has unique indexes on `username` and `phone` from migration `2026_08_27_050000_add_auth_columns_to_users.php`; `email` is unique from base migration.
- Password is hashed with `Hash::make()` before insert/update.
- Self-service registration uses `UserService::create()` and explicitly sends email verification notification.
- Admin user creation uses the same service but does not send verification email.
- Lock/unlock/reset-password are admin actions via `UserController` using `UserService` methods; activities are logged explicitly.
- Soft delete uses Eloquent `delete()`; restore uses `restore()`; force delete uses `forceDelete()`.
- Bulk actions use `BulkDeleteService::run()` with authz checks per item and skip guards for self/super-admin.
- Locked user list visibility: `User::withTrashed()` is used in index; locked state is visible if view renders it.
- After soft delete: user can no longer authenticate because `User::where(...)->first()` ignores soft-deleted rows by default.
- After restore: user can log in again if credentials valid and not locked.

### Assumptions not yet confirmed by live runtime
- Whether the user index view shows deleted/restored state indicators consistently.
- Whether admin-created users should receive verification email; current code does not send one.
- Whether changing a user's role requires cache/session invalidation for that user to see updated access.

## 4. Current Implementation

### Controllers / Services
- `UserController` delegates to `UserService` for create/update/lock/unlock/send-reset.
- `UserService::create()` hashes password, syncs roles, optionally provisions default license in `per_user` mode.
- `UserService::update()` batches field updates into one Eloquent `update()` call, then syncs roles.

### Validation
- `UserStoreRequest` / `UserUpdateRequest` enforce required fields, uniqueness, phone regex, min-12 password with confirmation on create, nullable password on update.
- `ProfileUpdateRequest` enforces unique phone ignoring current user.
- No database-level uniqueness override needed beyond migrations.

### Database
- `users.username` unique
- `users.email` unique
- `users.phone` unique nullable
- `users.deleted_at` nullable
- `users.locked_until` nullable
- `users.locked_permanently` boolean default false
- `users.last_login_at`, `users.last_login_ip` nullable

### Tests
- `UserManagementTest` covers create, invalid fields not explicitly asserted except via redirect, soft delete/restore, bulk soft/force delete, password change.
- `UserManagementLockResetTest` covers role update, unlock, reset link, audit logging, permanent lock, self-lock blocked, staff without permission blocked.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Admin creates user with unique fields | Success + redirect | Same | PASS |
| Admin creates user with duplicate email | Validation error | Same | PASS |
| Admin creates user with duplicate username | Validation error | Same | PASS |
| Admin creates user with duplicate phone | Validation error | Same | PASS |
| Concurrent duplicate user creation | Validation passes in two requests, DB unique index rejects one, throws QueryException | Should return validation error consistently | UNCLEAR/GAP |
| Admin updates user roles | Roles synced via `syncRoles()` | Same | PASS |
| Admin updates own account roles | Allowed | Allowed (expected) | PASS |
| Admin updates password during edit | Hashed and saved | Same | PASS |
| User soft deleted | `deleted_at` set, normal auth query returns null | Same | PASS |
| User restored | `deleted_at` cleared | Same | PASS |
| User force deleted | Row removed | Same | PASS |
| Locked user appears in user list | Yes (withTrashed + no locked filter shown) | Debatable; design question | DESIGN QUESTION |
| Permanent lock blocks login | Yes | Same | PASS |
| Unlock restores login | Yes | Same | PASS |
| Admin reset password sends link | Yes | Same | PASS |
| Self-service registration | Sends verification email | Same | PASS |
| Admin-created user receives verification email | No | Debatable | DESIGN QUESTION |
| Login after restore | Works if not locked | Same | PASS |
| Previous sessions after restore | Existing sessions rows remain if not deleted | Debatable | DESIGN QUESTION |
| Profile uniqueness/phone conflict | Unique enforced ignoring self | Same | PASS |
| Bulk delete self | Skipped by skip guard | Same | PASS |
| Bulk force delete self | Skipped by skip guard | Same | PASS |
| Staff without `user.delete` bulk action | 403 by route middleware | Same | PASS |
| Audit trail for create/update/delete/restore/force-delete | Only lock/unlock/reset-link explicitly logged | Other mutations not guaranteed | GAP |

## 6. Cross-Feature Dependencies

- RBAC: user actions gated by `user.*` permissions on routes; bulk action checks per item.
- Audit: only lock/unlock/reset-link explicitly audited; create/update/delete may not be.
- Plan/Billing: `UserService::create()` may provision default license when `license_mode=per_user`.
- Sessions: login behavior after restore/delete depends on auth query behavior and existing session rows.

## 7. Security Assessment

Strengths:
- Unique constraints at DB level for email/username/phone.
- Password always hashed, never plain.
- Self-delete/force-delete/lock are blocked.
- Admin reset password uses broker, not plaintext.
- Bulk delete checks auth per item, not just route.

Concerns:
- Concurrent user creation with same unique value can throw unhandled `QueryException` if validation and DB constraint collide under race; current tests don't cover this.
- No audit logging for user create/update/delete/restore/force-delete — only lock/unlock/reset-link are logged.
- No explicit invalidate-sessions-on-sensitive-change for user management actions (e.g., admin reset password does not terminate existing sessions).

## 8. Maintainability Assessment

- `UserService` is thin and shared between web + API controllers; good.
- `BulkDeleteService` is reused across users/roles/permissions; good.
- Duplicate uniqueness validation exists in `UserStoreRequest`, `UserUpdateRequest`, `ProfileUpdateRequest`, `RegisterRequest`; acceptable given different contexts.
- No service-layer event dispatch on user mutations; audit is partial.

## 9. UX Assessment

- User list includes soft-deleted users due to `withTrashed()`; if view does not differentiate deleted state clearly, admins may be confused.
- No visible badge/row style for locked accounts confirmed from code.
- Success/error flash messages are used; acceptable.

## 10. Identified Gaps

### GAP-USER-001
**Title:** Concurrent user creation can crash with unvalidated duplicate
**Severity:** Medium
**Category:** Data integrity / UX
**Evidence:** `UserStoreRequest` validates `unique:users,email` etc., but two parallel requests can pass validation and the DB unique index throws `QueryException`.
**Current behavior:** One request succeeds, one fails with 500 unless caught.
**Expected behavior:** Convert DB constraint violation into a 422 validation error for the relevant field.
**Impact:** Poor UX and potential noise in error monitoring.
**Likely root cause:** No DB-level exception handling mapping in controller/service.
**Affected components:** `UserService::create()`, `UserStoreRequest`, `RegisterRequest`.
**Related QA cases:** QA-041, QA-042, QA-043.

### GAP-USER-002
**Title:** Missing audit coverage for user CRUD mutations
**Severity:** Medium
**Category:** Audit / Maintainability
**Evidence:** `UserController` logs activity only for lock/unlock/send-reset; create/update/delete/restore/force-delete do not emit activity log entries.
**Current behavior:** Important user lifecycle changes are not in audit trail.
**Expected behavior:** All mutations should produce consistent audit entries (`user_created`, `user_updated`, `user_deleted`, `user_restored`, `user_permanently_deleted`).
**Impact:** Incomplete audit trail for security review and incident response.
**Likely root cause:** Partial adoption of activity logging; not enforced at service/action layer.
**Affected components:** `UserService`, `UserController`.
**Related QA cases:** QA-060, QA-061.

### GAP-USER-003
**Title:** Admin-created users do not receive verification email
**Severity:** Low
**Category:** Functional / UX
**Evidence:** `RegisterController` explicitly sends verification email; `UserService::create()` does not.
**Current behavior:** Admin-created users have `email_verified_at` null unless manually verified later.
**Expected behavior:** Depends on product decision. If admin creates user, verification email may be expected or admin-set verified.
**Impact:** Inconsistent onboarding behavior between admin and self-service flows.
**Likely root cause:** No defined policy; implementation split between controllers.
**Affected components:** `UserService`, `RegisterController`.
**Related QA cases:** QA-010, QA-041.

### GAP-USER-004
**Title:** Locked-user visibility/state not explicitly surfaced in user list UI
**Severity:** Low
**Category:** UX / Consistency
**Evidence:** Index uses `withTrashed()`; no code-level evidence of locked-state indicator styling in backend; view inspection needed.
**Current behavior:** Locked users may appear identical to active users in the table.
**Expected behavior:** Clear visual indicator for temporary/permanent lock and soft-deleted state.
**Impact:** Admin may miss locked accounts during triage.
**Likely root cause:** View does not include conditional locked/delete badges.
**Affected components:** `access.users.index` view.
**Related QA cases:** QA-049, QA-050, QA-045, QA-046.

## 11. Recommended Direction

- Add try/catch around `User::create()` to convert unique constraint violations to validation errors, or rely on DB-level ` Integrity constraint violation` handler.
- Add activity logging to `UserService` for create/update/delete/restore/force-delete.
- Decide policy for admin-created user verification and implement consistently.
- Add explicit locked/soft-deleted indicators in user list view for operational clarity.

## 12. Deferred / Open Questions

- Should an admin-triggered password reset invalidate existing sessions or API tokens?
- Should restored users be required to re-authenticate?
- Should user list continue including soft-deleted users by default, or move to an Archive filter?
