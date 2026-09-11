# Feature 01 — Authentication & Session Lifecycle

## 1. Scope

Included:
- Login (email/username/password)
- Invalid login handling
- Login throttling/rate limiting
- Locked user behavior (temporary + permanent)
- Logout
- Protected routes / auth middleware
- Session lifecycle (driver, lifetime, regeneration)
- Session invalidation
- Email verification dependency on login
- Failed login audit events
- Password reset token lifecycle
- Admin-triggered password reset

Explicitly out of scope:
- Billing/plan license boundaries affecting dashboard content (deferred)
- Notification UX surfaces (covered in FEATURE 11)

## 2. Related QA Cases

- QA-005 login success
- QA-006 invalid login
- QA-007 throttle behavior
- QA-008 logout
- QA-009 protected redirect
- QA-010 email verification
- QA-019 password change valid
- QA-020 password change invalid
- QA-049 lock user
- QA-050 unlock user
- QA-051 reset user password
- QA-078 API login valid
- QA-079 API login invalid
- QA-080 API me
- QA-084 API token CRUD
- QA-085 API password change
- QA-086 API email verify/resend
- QA-102 API token revocation immediate
- QA-103 user delete/restore/force-delete
- QA-104 lock/unlock login
- QA-105 password change login

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Login accepts `identifier` (email or username) + `password`.
- Failed login increments two counters in cache: `login:user:{identifier}` and `login:{ip}:{identifier}`.
- After 5 failed attempts against the account key, the user is locked for 15 minutes in DB (`users.locked_until`).
- Permanent lock is an admin action setting `locked_permanently=1`; unlock clears both flags.
- Successful login clears cache keys, resets `locked_until` to null, and records `last_login_at` + `last_login_ip`.
- Session is regenerated on successful login.
- Unverified email users are rejected after `Auth::attempt()` succeeds by explicitly logging out and throwing `ValidationException`.
- Failed auth events are audited via `LogAuthentication` listener.

### Assumptions not yet confirmed by live environment
- Actual `CACHE_DRIVER` value in `.env` determines whether throttles live in DB `cache` table or Redis.
- Whether `Auth::logoutOtherDevices()` invalidates remember-token cookies in addition to DB sessions.
- Whether `User::withTrashed()` find in lock/unlock can act on soft-deleted users (yes, by code).

## 4. Current Implementation

### Routes (web)
- `GET|POST /login` — guest only, POST throttled `10,15`.
- `POST /logout` — auth only.
- `GET /email/verify/{id}/{hash}` — signed URL, no auth middleware.
- `POST /email/verify/resend` — auth only.
- `GET|POST /forgot-password`, `/reset-password` — guest only, throttled.
- `GET|POST /register` — guest + `registration.enabled` middleware + throttled.

### Controllers
- `LoginController::store()` — full throttle + lock + verification guard + audit event + session regen.
- `LoginController::destroy()` — logout + invalidate session + regenerate CSRF token.
- `ForgotPasswordController` — broker send + token create/validate + audit on request.
- `RegisterController` — `UserService::create()` + explicit single verification email dispatch in DB transaction.

### Models
- `User` has `locked_until`, `locked_permanently`, `last_login_at`, `last_login_ip`.
- `isLocked()` returns true for permanent lock OR future `locked_until`.
- `isPermanentlyLocked()` returns `locked_permanently` bool.

### Auth guards/session
- Guard: `web` session driver.
- Session driver default: `database` (`sessions` table), lifetime 120 min, `expire_on_close=false`, `secure/http_only/same_site=lax` from env.
- Password broker: `users` using `password_reset_tokens` table, 60 min expiry.

### Events/Listeners
- `Login`, `Logout`, `Failed`, `PasswordReset`, `Verified` → `LogAuthentication` listener.
- Listener writes activity log and sends `AuditNotification` to the account owner when event user exists.

### API
- `POST /api/v1/login` throttled; no documented lockout behavior in API tests.
- `POST /api/v1/logout`, `password/change`, `email/verify/resend` authenticated via Sanctum.
- API login implementation is in `Api/AuthApiController` extending `AuthController`; need to inspect if it shares the web lock/throttle behavior.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Valid email login | Redirects to dashboard, session created | Same | PASS |
| Valid username login | Redirects to dashboard, session created | Same | PASS |
| Phone-like login | Rejected as identifier | Same | PASS |
| 5 failed attempts from same IP/account | Account locked 15m in DB | Same | PASS |
| 5 failed attempts with IP rotation | Account-centric throttle still locks | Same | PASS |
| Login while temporarily locked | Returns locked message with countdown | Same | PASS |
| Admin permanent lock | Blocks login with contact-admin message | Same | PASS |
| Admin unlock | Clears both lock flags | Same | PASS |
| Successful login after expired lock | Unlocks account, logs in | Same | PASS |
| Unverified email login | Logs out immediately, 422 on email field | Same | PASS |
| Logout | Session invalidated, CSRF regenerated | Same | PASS |
| Forgot password | Token created + status message + audit | Same | PASS |
| Password reset valid | Password updated, token cleared | Same | PASS |
| Password reset invalid/expired | ValidationException mapped to message | Same | PASS |
| Registration when enabled | Creates user + sends verification email | Same | PASS |
| Registration when disabled | 404 on route | Same | PASS |
| Failed login audit | `login_failed` activity logged | Same | PASS |
| Successful login audit | `login_success` activity logged | Same | PASS |
| Session list | Lists current user's rows from `sessions` | Same | PASS |
| Logout others without password | Deletes other session rows for user | Same | PASS |
| Logout others with password | Calls `Auth::logoutOtherDevices()` | Same | PASS |
| Password change | Updates password, deletes Sanctum tokens, calls `logoutOtherDevices()` | Same | PASS |
|| API login | Returns token + user JSON | Same | PASS |
|| API lockout after failed attempts | IP throttle only; no account lock, no `locked_until` DB update | Documented API-only behavior | EXPECTED BEHAVIOR |
| Same credentials from new browser/device | New session is created; old sessions remain | Need product decision | DESIGN QUESTION |
|| Same credentials while locked | Blocked in all browsers/devices | Same | PASS |
|| Login failure UI state | Session error only; no persistent lock modal/state shown before redirect | UX gap | GAP-AUTH-003 (existing) |
|| API lockout scope | Only IP-based throttle; no account lockout enforcement | Should be documented | DESIGN QUESTION |
| Deleted user login | Blocked by Eloquent query result `null` | Expected fail-closed | PASS |
| Restored user login | Allowed if credentials valid and not locked | Expected | PASS |

## 6. Cross-Feature Dependencies

- RBAC: admin lock/unlock/reset-password actions are gated by `user.lock`/`user.edit`.
- Feature flags: sessions feature gates `/sessions` and `/sessions/logout-others`.
- Audit: authentication events feed the audit trail and notification system.
- Profile/password: password change interacts with web sessions and API tokens.

## 7. Security Assessment

Strengths:
- Fail-closed registration when disabled (`404`).
- Account-centric throttling survives IP rotation.
- Permanent lock requires explicit admin action.
- Unverified emails cannot complete login.
- Sessions invalidated on logout.
- Password reset token is short-lived (60 min) and single-use.
- CSRF excluded only for the PG webhook endpoint, not auth forms.
- Rate limiting exists on auth endpoints (`10,15`).

Concerns:
- Two parallel throttles may double-write cache without atomic coordination; acceptable under low concurrency but not guaranteed under high concurrency.
- `logoutOthers` accepts optional password; without password it only deletes DB session rows, not remember-token cookies or other guards.
- API auth login path has no visible lockout behavior in current code/tests; may become an inconsistency.
- `Auth::logoutOtherDevices()` usage assumes current password hash compatibility; if password hashing algorithm changes, old hashes may not validate.

## 8. Maintainability Assessment

- `LoginController::store()` handles auth + throttle + lock + verification + audit + session in one method; acceptable for a single action, but any change touches all concerns together.
- `LogAuthentication` listener uses `$event->credentials['identifier']` for `Failed` event; this works for web `LoginController` because it passes a custom credential key? Need to verify Laravel `Failed` event exposes credentials; if not, listener may silently log null for API paths.

## 9. UX Assessment

- Lock messages show countdown via `diffInSeconds`; good.
- No dedicated locked-user modal on the login page; user sees only a red validation message inline. For admin-initiated permanent locks, message is "Contact an administrator", but no UI differentiates temporary vs permanent beyond wording.
- Password toggle aria-label is hardcoded English (`Show password`/`Hide password`); violates i18n convention.

## 10. Identified Gaps

### GAP-AUTH-001
**Title:** API login lacks documented lockout behavior
**Severity:** High
**Category:** Security / API
**Evidence:** Web login has account lock after 5 failures; `AuthApiController` extends `AuthController` but API auth tests only cover valid/invalid login.
**Current behavior:** Unknown whether failed API login triggers same lock/throttle enforcement.
**Expected behavior:** API auth should either share the same account lock semantics or have explicit documented behavior.
**Impact:** API clients may bypass lockout policy.
**Likely root cause:** API auth path inspected separately; lock logic not confirmed in API controller/tests.
**Affected components:** `Api/AuthApiController`, API auth tests.
**Related QA cases:** QA-078, QA-079, QA-104.
**Dependencies:** None.

### GAP-AUTH-002
**Title:** "Logout others" password confirmation is optional
**Severity:** Medium
**Category:** Security / UX
**Evidence:** `LogoutOthersRequest` allows `nullable|string`; controller conditionally calls `logoutOtherDevices()` only if password is filled.
**Current behavior:** Any user can delete all other DB sessions without re-authenticating.
**Expected behavior:** Security-sensitive action should require password confirmation, or at minimum enforce it consistently across UI.
**Impact:** Lost/stolen session can terminate other legitimate sessions without proving identity again.
**Likely root cause:** Feature was shipped with optional password UX for convenience.
**Affected components:** `SessionController::logoutOthers`, `LogoutOthersRequest`, `settings/sessions.blade.php`.
**Related QA cases:** QA-054, QA-055.
**Dependencies:** None.

### GAP-AUTH-005
**Title:** API login does not enforce account lockout / `locked_until`
**Severity:** High
**Category:** Security / API
**Evidence:** `Api/AuthController::login()` checks only IP throttle + password; no `user->isLocked()` check and no `locked_until`/`locked_permanently` enforcement.
**Current behavior:** Locked users can still obtain API tokens.
**Expected behavior:** API auth should enforce the same account lock semantics as web login.
**Impact:** Bypass of account-level security control.
**Likely root cause:** API auth path was implemented separately without reusing `LoginController` lock/throttle logic.
**Affected components:** `app/Http/Controllers/Api/AuthController::login`, `LoginApiRequest`.
**Related QA cases:** QA-078, QA-079, QA-104.

### GAP-AUTH-003
**Title:** Locked user state is not surfaced as a dedicated UI state
**Severity:** Medium
**Category:** UX
**Evidence:** `LoginController` throws `ValidationException` with message; `auth/login.blade.php` renders only inline `$errors->first()` alert.
**Current behavior:** User sees error text but no explicit locked modal or visual distinction between temporary vs permanent lock.
**Expected behavior:** Clear locked-state messaging before showing login form, ideally differentiating retry-after vs contact-admin.
**Impact:** Support overhead; users may retry aggressively and consume throttle capacity.
**Likely root cause:** Implementation focused on backend enforcement; login view has no locked-state branch.
**Affected components:** `LoginController`, `auth.login`, translation keys.
**Related QA cases:** QA-005, QA-006, QA-049, QA-050, QA-104.
**Dependencies:** Phase 1 core behavior already fixed; UI enhancement remains.

### GAP-AUTH-004
**Title:** Hardcoded English in password toggle aria-label
**Severity:** Low
**Category:** i18n
**Evidence:** `auth/login.blade.php` line 71 sets `aria-label` to literal `'Hide password'`/`'Show password'`.
**Current behavior:** Toggle label is always English.
**Expected behavior:** Use `ui('show_password')` / `ui('hide_password')` or equivalent.
**Impact:** Screen-reader users in non-English locales get wrong language label.
**Likely root cause:** Dynamic attribute update bypassed translation helper.
**Affected components:** `resources/views/auth/login.blade.php`.
**Related QA cases:** QA-021.
**Dependencies:** Translation keys must exist.

## 11. Recommended Direction

- Confirm API lockout behavior in code; align with web semantics or explicitly document the difference.
- Decide whether "logout others" must always require password confirmation; if yes, make `password` required and update tests/UI.
- Add a locked-state message block in `auth/login.blade.php` to distinguish temporary/permanent lock without relying solely on validation errors.
- Fix hardcoded aria-label strings to use `ui()` keys.

## 12. Deferred / Open Questions

- Should the application invalidate older sessions when a user logs in from a new device/browser? Current behavior: no invalidation; multiple concurrent sessions are allowed.
- Should "remember me" sessions be treated differently from session invalidation actions?
