# Phase 6 — Profile + Password + Verification

## Completed
- Verified self-service profile update emits `profile_updated` audit log from `ProfileController::update()` and `ProfileApiController::update()` via `Auditable` trait, with performedOn target user and IP/user_agent properties.
- Verified self-service password change emits `password_changed` audit log from `ProfileController::changePassword()` and `ProfileApiController::changePassword()` via `Auditable` trait; change also revokes other devices/sessions and deletes Sanctum tokens for API.
- Verified email verification flow remains enforced: unverified users are rejected at login (`LoginController::store()`), and admin-reset-link path already logs `user_reset_link_sent`; web verify path logs `email_verified`, API verify path logs `email_verified`, resend path logs `verification_resent` / `password_reset_request` where applicable.
- Verified existing profile UI and API endpoints are reachable and covered by tests; password validation rules and uniqueness checks remain intact.

## Changes Made
- `app/Http/Controllers/ProfileController.php`: add `Auditable` trait; emit `profile_updated` on profile update and `password_changed` on password change.
- `app/Http/Controllers/Api/ProfileApiController.php`: add `Auditable` trait; emit `profile_updated` on profile update and `password_changed` on password change.
- `docs/qa/phase-reports/phase-06-profile-password-verification.md`: add Phase 6 report.
- `docs/qa/remediation-tracker.md`: mark Phase 6 `complete` and attach report.

## Root Cause Addressed
- Previously, self-service profile and password mutations had no controller-layer audit entries despite being sensitive user actions. Audit is now emitted from the same controllers handling the HTTP request, keeping `performedOn` = `$user`.

## Tests
- Command: `php artisan test --filter="ProfileTest"`
- Result: 6 passed, 15 assertions.

## Manual QA
- Profile update page and API both redirect/return updated user and emit `profile_updated`.
- Password change page and API both enforce current password + min 12 + confirmation, invalidate other sessions/devices, and emit `password_changed`.
- Login still rejects unverified email outright before granting access.
- Verification resend still shows correct feedback and emits `verification_resent` on API.

## Security Verification
- Password change requires `current_password`; revokes other sessions/tokens.
- Profile update preserves phone uniqueness validation and route-level auth.
- Email verification flow unchanged and still blocks unverified accounts from login.

## Regressions Checked
- No auth, session, or RBAC behavior changes beyond added audit logging.
- Existing profile/password tests still pass.

## Remaining Gaps
- None for Phase 6 scope.

## Design Decisions Required
- None pending.

## Files Changed
- `app/Http/Controllers/ProfileController.php`
- `app/Http/Controllers/Api/ProfileApiController.php`
- `docs/qa/phase-reports/phase-06-profile-password-verification.md`
- `docs/qa/remediation-tracker.md`

## Commit Recommendation
```
feat(profile): add audit logs for profile update and password change in web and API
```

## Ready for Next Phase
YES
