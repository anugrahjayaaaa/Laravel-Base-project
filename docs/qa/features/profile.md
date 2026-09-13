# Feature 05 — Profile

## 1. Scope

Included:
- Profile read
- Profile update (web + API)
- Validation
- Password change (web + API)
- Locale interaction
- Email verification interaction
- Authorization
- Session invalidation expectations

Explicitly out of scope:
- Plan/billing visibility on profile
- Notification settings if any

## 2. Related QA Cases

- QA-016 profile prefilled
- QA-017 profile update
- QA-018 profile invalid input
- QA-022 API profile read
- QA-023 API profile update
- QA-085 API password change
- QA-105 password change login

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Web profile page shows `auth()->user()` with name, phone, etc.
- Web profile update allows name + phone; phone uniqueness validated ignoring current user.
- Password change requires `current_password`, min-12 new password with confirmation.
- On password change:
  - Updates password hash.
  - Deletes Sanctum API tokens via `$user->tokens()->delete()`.
  - Calls `auth()->logoutOtherDevices(current_password)`.
- API profile endpoints:
  - `GET /api/v1/profile` returns current user JSON.
  - `PUT /api/v1/profile` updates current user fields.
  - `POST /api/v1/profile/password` changes password.
- No tests found for API profile read/update behavior in current test set beyond basic endpoint access.

## 4. Current Implementation

### Controllers
- `ProfileController::show/update/changePassword` for web.
- `ProfileApiController` for API endpoints (read/update/password).

### Requests
- `ProfileUpdateRequest`: name required, phone nullable+unique regex.
- `PasswordChangeRequest`: current_password required, password min:12 confirmed.

### Sessions
- Password change does not explicitly invalidate web sessions except via `logoutOtherDevices()`.
- `logoutOtherDevices()` invalidates other web sessions but not current session.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Profile page read | Shows current user | Same | PASS |
| Profile update valid | Redirect success | Same | PASS |
| Profile update duplicate phone | 422 | Same | PASS |
| Password change valid | Updates password, deletes tokens, logs out other devices | Same | PASS |
| Password change invalid current password | 422 | Same | PASS |
| Password shorter than 12 chars | 422 | Same | PASS |
| API profile read | JSON user | Same | PASS |
| API profile update | JSON updated user | Need explicit test | UNCLEAR |
| API password change | Updates password | Need explicit test | UNCLEAR |
| Email change | Not supported | Debatable | DESIGN QUESTION |

## 6. Cross-Feature Dependencies

- Auth: password change affects web session + API tokens.
- API: profile endpoints authenticated via Sanctum.

## 7. Security Assessment

- `current_password` rule enforces re-authentication.
- Password change invalidates API tokens and other web sessions; current session remains.

## 8. Maintainability Assessment

- Separate web/API controllers for profile is consistent with project pattern.
- Password change logic duplicated across web/API? Need confirm API controller implementation.

## 9. UX Assessment

- Password change success message present.
- No visible email change flow in profile.

## 10. Identified Gaps

### GAP-PROFILE-001
**Title:** API profile/password behavior not explicitly tested
**Severity:** Low
**Category:** API / Test coverage
**Evidence:** No API-specific profile tests for validation/errors/token invalidation.
**Current behavior:** Unknown if API password change invalidates tokens consistently.
**Expected behavior:** Covered by tests or documented.
**Impact:** Regression risk.
**Likely root cause:** API profile endpoints added without dedicated tests.
**Affected components:** `ProfileApiController`, API tests.
**Related QA cases:** QA-022, QA-023, QA-085.

## 11. Recommended Direction

- Add API profile tests for update validation and password-change token invalidation.

## 12. Deferred / Open Questions

- Should email change be supported from profile with reverification?
- Should password change also invalidate current session?
