# Feature 16 — API Surface

## 1. Scope

Included:
- Authentication
- Authorization
- Validation
- Rate limiting
- Token behavior
- Token scopes
- Token revocation
- Response consistency
- Error semantics
- User isolation
- Feature flag behavior
- Permission behavior

Explicitly out of scope:
- Billing APIs (deferred)
- Plan APIs (deferred)

## 2. Related QA Cases

- QA-022 API profile read
- QA-023 API profile update
- QA-074 API flag behavior
- QA-078 API login valid
- QA-079 API login invalid
- QA-080 API me
- QA-081 API users no perm
- QA-082 API users flag off
- QA-083 API roles/permissions gating
- QA-084 API token CRUD
- QA-085 API password change
- QA-086 API email verify/resend
- QA-102 API token revocation immediate

## 3. Current Runtime Behavior

### Observed (from code + tests)
- API uses Sanctum bearer tokens.
- Public API endpoints: login, forgot-password, reset-password, email/verify.
- Authenticated API endpoints: me, logout, password/change, profile, sessions, api-tokens, notifications, features, audit, users, roles, permissions.
- API routes do NOT use `feature:` middleware for most resources; route definitions rely on `can:` gates.
- API login returns token + user JSON.
- API logout deletes all user tokens.
- API password change deletes all tokens + calls `logoutOtherDevices()`.
- API tokens created with `['mobile']` scope only.
- Tests cover basic happy paths plus permission denial.

## 4. Current Implementation

### Routes
- `routes/api.php` v1 prefix.
- Auth routes throttled.
- Feature list route lacks manager gate.

### Controllers
- Separate API controllers for auth, users, roles, permissions, profile, sessions, tokens, audit, notifications, features.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| API login valid | Token + user JSON | Same | PASS |
| API login invalid | 422 | Same | PASS |
| API me without token | 401 | Same | PASS |
| API me with token | User JSON | Same | PASS |
| API users without perm | 403 | Same | PASS |
|| API users flag off | 200 OK | Route lacks `feature:` middleware | GAP-API-001 |
| API roles/permissions gating | 403 | Same | PASS |
| API token create | Plain token once | Same | PASS |
| API token delete | Revoked | Same | PASS |
| API logout | Tokens deleted | Same | PASS |
| API password change | Password updated, tokens deleted | Same | PASS |
| API email verify/resend | Works | Same | PASS |
| API feature list | Open to any authenticated user | Should require manager | GAP |
| API token scopes | Only `mobile` | Single scope acceptable | PASS |

## 6. Cross-Feature Dependencies

- Auth: API auth depends on Sanctum token model.
- RBAC: API routes gated by `can:`.
- Feature flags: most API routes lack `feature:` middleware.

## 7. Security Assessment

- Bearer tokens revocable.
- Password change invalidates tokens.
- No API rate limiting beyond login/reset.
- Potential information leak via open feature list.

## 8. Maintainability Assessment

- API controllers mirror web controllers; acceptable.

## 9. UX Assessment

- Consistent JSON responses.

## 10. Identified Gaps

### GAP-API-001
**Title:** API module routes lack feature flag middleware
**Severity:** Medium
**Category:** API / Security
**Evidence:** `routes/api.php` applies only `can:` gates to users/roles/permissions/sessions/api-tokens; no `feature:` middleware on module resources.
**Current behavior:** API resources accessible even if feature flag disabled.
**Expected behavior:** Feature-off should 404 for API too.
**Likely root cause:** API routes added without feature gating.
**Affected components:** `routes/api.php`.
**Related QA cases:** QA-082, QA-083.

### GAP-API-002
**Title:** API feature list exposed to all authenticated users
**Severity:** Medium
**Category:** Authorization / API
**Evidence:** `routes/api.php` line 53 defines `GET features` with `auth:sanctum` only; no `can:feature.manage`.
**Current behavior:** Any token holder can list feature flags.
**Expected behavior:** Restrict to managers.
**Likely root cause:** Missing middleware.
**Affected components:** `routes/api.php`, `FeatureApiController::index`.
**Related QA cases:** QA-074.

## 11. Recommended Direction

- Add `feature:` middleware to API module routes.
- Restrict API feature list to `can:feature.manage`.

## 12. Deferred / Open Questions

- Should API support token scopes beyond `mobile`?
