# Feature 10 — Audit Logs

## 1. Scope

Included:
- Audit creation behavior
- Actor/target/action/metadata
- Authorization for audit access
- Filtering/pagination
- Export
- Cross-feature coverage
- Sensitive information handling

Explicitly out of scope:
- Plan/billing audit specifics beyond existing behavior
- Notification integration details

## 2. Related QA Cases

- QA-060 audit record created
- QA-061 audit export
- QA-115 regression checklist

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Audit uses Spatie Activitylog.
- Events logged:
  - `login_success`, `logout`, `login_failed`, `password_reset`, `email_verified` (via `LogAuthentication`).
  - `user_locked`, `user_unlocked`, `user_reset_link_sent` (explicit in `UserController`).
  - `password_reset_request` (explicit in `ForgotPasswordController`).
- Audit list/export gated by `feature:audit` + `can:audit.view`.
- Export returns activity data.
- Tests confirm audit entries exist for login success, password reset request, unlock, lock, reset-link.

## 4. Current Implementation

### Listeners
- `LogAuthentication` writes activity + notifies user via `AuditNotification`.

### Controllers
- `AuditController::index/export`.
- Notifications listed separately under audit feature flag in routes.

### Data model
- Activity log entries include log_name, description, properties (ip/user_agent/identifier), causer, subject.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Login success audited | Yes | Same | PASS |
| Login failed audited | Yes | Same | PASS |
| Logout audited | Yes | Same | PASS |
| Password reset request audited | Yes | Same | PASS |
| Password reset completion audited | Via event | Same | PASS |
| Email verified audited | Via event | Same | PASS |
| Lock/unlock audited | Yes | Same | PASS |
| Admin reset-link sent audited | Yes | Same | PASS |
| User create/update/delete audited | No | Should be | GAP |
| Role/permission create/update/delete audited | No | Should be | GAP |
| Feature toggle audited | No | Should be | GAP |
| Audit export | Available | Same | PASS |
| Unauthorized audit access | 403/404 | Same | PASS |

## 6. Cross-Feature Dependencies

- Auth events feed audit.
- User/Role/Permission mutations currently do not consistently feed audit.

## 7. Security Assessment

- Audit entries include IP/user_agent; acceptable for internal admin tool.
- No sensitive payload fields observed.

## 8. Maintainability Assessment

- `LogAuthentication` centralizes auth audit.
- Other mutations rely on ad-hoc explicit logging.

## 9. UX Assessment

- Audit page/export available under monitoring menu.

## 10. Identified Gaps

### GAP-AUDIT-001
**Title:** Incomplete audit coverage for non-auth mutations
**Severity:** Medium
**Category:** Audit / Security
**Evidence:** Only auth, lock/unlock, reset-link, and password-reset-request are logged; user/role/permission/feature mutations lack activity entries.
**Current behavior:** Security-critical state changes are missing from audit trail.
**Expected behavior:** Consistent audit entries for all admin mutations.
**Likely root cause:** Partial implementation; no enforced policy.
**Affected components:** `UserService`, `RoleController`, `PermissionController`, `FeatureController`.
**Related QA cases:** QA-060, QA-061, QA-115.

## 11. Recommended Direction

- Add activity logging in service layer or controllers for user/role/permission/feature mutations.
