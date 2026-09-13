# Feature 06 — Password & Recovery

## 1. Scope

Included:
- Change own password (web + API)
- Forgot password
- Reset password (valid/invalid/expired)
- Password policy
- Session invalidation after password change/reset
- Admin-triggered password reset
- Audit of password events

Explicitly out of scope:
- Plan/billing password requirements
- MFA/2FA

## 2. Related QA Cases

- QA-011 forgot password
- QA-012 reset password valid
- QA-013 reset password invalid
- QA-019 password change valid
- QA-020 password change invalid
- QA-051 reset user password
- QA-055 logout others
- QA-085 API password change
- QA-105 password change login

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Web forgot password: validate email, send reset link via broker, audit `password_reset_request`.
- Web reset password: validate token + email, set new password, clear token, audit `password_reset`.
- Web password change: require current_password, min 12 chars, confirmed; update hash, delete Sanctum tokens, call `logoutOtherDevices()`.
- API forgot/reset password: same broker flow, JSON responses, audit on request.
- Admin can trigger reset link from user management (`UserService::sendResetPassword`).
- Password broker uses `password_reset_tokens` table, 60 min expiry.

## 4. Current Implementation

### Controllers
- `ForgotPasswordController::store/update`
- `ProfileController::changePassword`
- `Api/AuthApiController::forgotPassword/resetPassword`
- `UserController::sendResetPassword`

### Validation
- `PasswordEmailRequest` validates email.
- `PasswordResetRequest` validates token/email/password min:12 confirmed with uppercase/lowercase/digit/symbol.
- `PasswordChangeRequest` validates current_password + min:12 confirmed.

### State changes
- Reset: updates password, clears token row.
- Change: updates password, deletes API tokens, invalidates other web sessions.

### Audit
- `password_reset_request` logged in `ForgotPasswordController`.
- `PasswordReset` event logged by `LogAuthentication`.
- Admin reset-link action logged explicitly as `user_reset_link_sent`.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Forgot password valid | Reset link sent + status + audit | Same | PASS |
| Forgot password invalid user | 422 mapped message | Same | PASS |
| Forgot password throttle | Broker throttle mapped | Same | PASS |
| Reset password valid | Password updated, token cleared | Same | PASS |
| Reset password invalid token | 422 mapped token error | Same | PASS |
| Reset password expired token | Invalid token error | Same | PASS |
| Change own password valid | Updates, deletes tokens, logs out others | Same | PASS |
| Change own password invalid current | 422 | Same | PASS |
| Change own password short | 422 | Same | PASS |
| Admin reset link | Broker sends email + audit | Same | PASS |
| Password policy | min 12 + upper/lower/number/symbol across registration/reset/change | Consistent | EXPECTED BEHAVIOR |

## 6. Cross-Feature Dependencies

- Audit: password events feed activity log.
- Sessions: password change invalidates other sessions + API tokens.
- Mail: reset/verification emails depend on `MAIL_*` config.

## 7. Security Assessment

- Token is hashed in DB, single-use, short-lived.
- Password confirmation required for sensitive changes.
- Rate limiting on forgot/reset endpoints.

Concerns:
- Reset password allows min 8 chars, weaker than registration policy (min 12 + complexity).
- Password reset does not invalidate existing web sessions explicitly.

## 8. Maintainability Assessment

- Password reset status mapping centralized in `ForgotPasswordController::statusMessage()`.
- API and web reset paths reuse broker; good.

## 9. UX Assessment

- Status messages resolved to app language, no raw `passwords.*` leakage.
- Password toggle UX present on login form.

## 10. Identified Gaps

### GAP-PASS-001
**Title:** Password reset does not invalidate existing web sessions
**Severity:** Low
**Category:** Security / Consistency
**Evidence:** `ForgotPasswordController::update()` updates password and clears token, but does not invalidate other sessions or Sanctum tokens.
**Current behavior:** Active sessions remain valid after password reset.
**Expected behavior:** Depends on product decision; profile password change invalidates other sessions, but reset path intentionally does not.
**Impact:** A stolen session can persist after password reset unless the user explicitly logs out elsewhere.
**Likely root cause:** Reset flow is treated as recovery, not re-authentication.
**Affected components:** `ForgotPasswordController::update`, API reset equivalent.
**Related QA cases:** QA-012, QA-055.
**Dependencies:** Design decision required.

### GAP-PASS-002
**Title:** Password policy documentation mismatch
**Severity:** Informational
**Category:** Documentation
**Evidence:** Earlier docs stated reset used `min:8`; implementation uses `min:12` + complexity regex.
**Current behavior:** Code is consistent; docs were stale.
**Expected behavior:** Documentation matches implementation.
**Likely root cause:** Doc not updated when reset form was hardened.
**Affected components:** `docs/qa/features/password-recovery.md`.
**Related QA cases:** QA-012, QA-013.

## 11. Recommended Direction

- Unify password minimum to at least 12 across registration, change, and reset.
- Invalidate other web sessions on password reset, consistent with change-password flow.

## 12. Deferred / Open Questions

- Should password reset also invalidate Sanctum tokens?
- Should we enforce symbol/uppercase/lowercase requirements on reset?
