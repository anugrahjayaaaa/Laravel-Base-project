# Feature 07 — Email Verification

## 1. Scope

Included:
- Registration verification
- Verification email sending
- Verify endpoint (web + API)
- Resend verification
- Already verified behavior
- Expired/invalid verification handling
- Email change interaction
- User creation from admin
- Login restrictions for unverified users

Explicitly out of scope:
- Plan/billing interaction with verification
- Notification delivery issues beyond verification emails

## 2. Related QA Cases

- QA-010 email verification
- QA-078 API login valid
- QA-086 API email verify/resend

## 3. Current Runtime Behavior

### Observed (from code + tests)
- User model implements `MustVerifyEmail`.
- Self-service registration explicitly sends verification email via `$user->sendEmailVerificationNotification()`.
- Admin user creation does not send verification email.
- Web verify endpoint: `GET /email/verify/{id}/{hash}` uses signed URL validation.
- API verify endpoint: validates signature, marks verified, returns JSON.
- Resend verification available to authenticated users.
- Login controller rejects unverified emails after `Auth::attempt()` by explicitly logging out and throwing validation exception.
- `Verified` event is audited by `LogAuthentication`.

## 4. Current Implementation

### Controllers
- `LoginController::verify/resendVerification`
- `RegisterController::store` sends email.
- `Api/AuthApiController::verifyEmail/resendVerification`

### Events/Listeners
- `Verified` -> `LogAuthentication` listener logs `email_verified`.

### Mail
- Uses Laravel default `VerifyEmail` notification; requires `MAIL_*` config.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Self-register with valid email | Verification email sent + redirect to login | Same | PASS |
| Self-register with invalid email format | Validation error | Same | PASS |
| Click valid verification link | Email verified + redirect | Same | PASS |
| Click expired/invalid link | 403 invalid link | Same | PASS |
| Resend verification for unverified user | Email sent | Same | PASS |
| Resend verification for verified user | 400 already verified | Same | PASS |
| Login with unverified email | Blocked after attempt + logout | Same | PASS |
| Admin-created user verification | No email sent | Debatable | DESIGN QUESTION |

## 6. Cross-Feature Dependencies

- Auth: unverified users cannot complete login.
- Mail: verification depends on `MAIL_*`.
- Audit: verification event is logged.

## 7. Security Assessment

- Signed URL prevents forgery.
- Unverified users cannot log in even though `MustVerifyEmail` alone does not block `Auth::attempt()`; explicit guard added.

## 8. Maintainability Assessment

- Verification flow is standard Laravel with minor customizations.
- API and web paths are separate but consistent.

## 9. UX Assessment

- Registration shows status to verify email.
- Login page shows generic invalid credentials for unverified user; could be clearer.

## 10. Identified Gaps

### GAP-VERIFY-001
**Title:** Admin-created users do not receive verification email
**Severity:** Low
**Category:** Functional / UX
**Evidence:** `RegisterController` sends email; `UserService::create()` does not.
**Current behavior:** Admin-created users have `email_verified_at` null unless manually verified later.
**Expected behavior:** Consistent with product decision.
**Likely root cause:** No defined policy for admin-created user verification.
**Affected components:** `UserService`, admin user creation flow.
**Related QA cases:** QA-010, QA-041.

## 11. Recommended Direction

- Define policy: either auto-verify admin-created users or send verification email.

## 12. Deferred / Open Questions

- Should email change require re-verification?
