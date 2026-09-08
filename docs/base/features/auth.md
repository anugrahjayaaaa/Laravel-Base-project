---
id: BASE-002
name: Auth
status: implemented
---

# Authentication

## User fields
- `username` (unique, login identifier #1)
- `phone` (unique, stored but NOT a login identifier — removed from login flow)
- `email` (unique, verification/reset)
- `password` (argon2id/bcrypt, NEVER plaintext)
- `email_verified_at`, `phone_verified_at`
- timestamps + `deleted_at` (soft delete)

## Password rules (VERY STRONG)
- Minimum 12 characters; required: uppercase, lowercase, digit, symbol.
- Must not contain username/email/phone.
- Confirmation required (`password_confirmation` must match).
- Argon2id default Laravel cost.

## Flow
- Login: **email OR username** + password (phone removed).
- Lockout: 5 consecutive fails → account locked 15m (DB column `users.locked_until`, survives IP rotation; auto-unlocks when it expires). IP+identifier throttle (cache key `login:{ip}:{identifier}`) still applies as a first layer. Fails are not audited individually (throttle state lives in cache).
- Locked account behavior: temporary lock shows as "locked" and blocks login until expiry; permanent lock shows as "perm locked" and blocks login with generic administrator contact message. API login enforces lock state before password check.
- Admin can **lock** permanently via `POST /users/{user}/lock` (sets `locked_permanently=true`; only unlock clears it), **unlock** via `POST /users/{user}/unlock` (clears `locked_until` and `locked_permanently`), and **send a reset link** via `POST /users/{user}/reset-password` (uses the `users` password broker; requires `MAIL_*` to deliver). Both lock/unlock require the `user.lock` permission (self-lock blocked); reset-password requires `user.edit`. All three actions are written to `activity_log` (`user_locked`, `user_unlocked`, `user_reset_link_sent`). Permanent lock shows as "perm locked" (red) and blocks login with "Contact an administrator"; the 15m auto-lock shows as "locked" (warning).
- Rate limit: /login, /password/* (e.g. 10/15m).
- Reset: hashed token, 60m expiry, single-use, stored in DB table `password_reset_tokens`.
- Verify: email activation before full access. New self-service registrants (`MustVerifyEmail`) are **rejected at login** until they click the verification link (not just gated from protected routes).
- Logout: invalidate session + regenerate CSRF token; audit.
- Change password: old password required; audit; revoke other sessions.

## Admin user creation
- Admin can create users from user management; password is generated as a random strong value and shown in the create form for copy, then stored hashed.
- Admin-created users receive email verification; identity credentials are sent via notification/email, not returned in API responses.
- Username and email uniqueness is enforced in FormRequest validation and DB unique indexes; duplicate create under race condition is handled at exception layer.

## Delete/restore auth behavior
- Soft delete prevents login because default auth query excludes `deleted_at` rows.
- Restore clears `deleted_at` and allows login again if credentials are valid and the account is not locked.
- Force delete removes the row permanently.

## User CRUD audit logging
- User mutations are logged from controller layer via `App\Http\Controllers\Concerns\Auditable`: `user_created`, `user_updated`, `user_deleted`, `user_restored`, `user_permanently_deleted`.
- Lock, unlock, and admin reset-password are logged separately: `user_locked`, `user_unlocked`, `user_reset_link_sent`.
- Auth controllers use the same trait: `LoginController` logs `account_locked_auto`, `email_verified`; `ForgotPasswordController` logs `password_reset_request`.

## Self-service
- Profile: name, avatar, phone; change password; view active sessions.
- Session mgmt: list devices, logout all other devices.
- **Registration (toggle):** self-service sign-up available only when `Setting::get('registration_enabled')` is truthy. When disabled, `/register` routes 404 (fail-closed via `RegistrationEnabled` middleware). New users created with email verification pending (`MustVerifyEmail`).

## Registration
- Gate: `Setting::get('registration_enabled')` — default **disabled** (no public sign-up).
- default_plan / default_role : set in **System → Settings** (feature.manage); `default_role` auto-assigned to new self-service registrants. `UserService::rolesFromInput()` falls back to this when `roles` is empty.
- Toggle: `UPDATE settings SET value='1' WHERE key='registration_enabled';`
- Route: `GET|POST /register` (guest, throttle:10,15) — middleware `registration.enabled`.
- Validation: name, username (unique), email (unique), phone (nullable/unique), password min:12 + strong (upper/lower/number/symbol).
- Flow: create user via `UserService::create()` → fire `Registered` event → send verification email → redirect to login with status "verify your email".
- New users have **no roles assigned** by default

## Security gate (must be green)
- Boundary validation; parameterized SQL; output escaped.
- Cookie: httpOnly, secure, sameSite=lax.
- No secrets in code/VCS (.env gitignored).
- Rate limit active on auth endpoints.

---

## Implementation Reference (functions)

### `App\Http\Controllers\Auth\LoginController`

**`show()`**
- Purpose: render login page.
- Input: none.
- Output: `view('auth.login')`.

**`store(Request $request)`**
- Purpose: authenticate user via email or username, start session.
- Input: `identifier` (email|username), `password`, optional `remember` (bool).
- Output: redirect to dashboard (success) or back with `identifier` error.
- Throws: `ValidationException` on bad credentials / lockout.
- State:
  - Rate limiter key `login:{ip}:{identifier}` → **CACHE** (driver `database` → table `cache`).
    Max 5 hits; on fail adds 900s (15m) window. Cleared on success.
  - Session regenerated → **DB table `sessions`** (`config('session.driver')`).
  - Fires `Illuminate\Auth\Events\Login` (so audit observer logs it).

**`destroy(Request $request)`**
- Purpose: logout, destroy session.
- Input: none (auth'd user).
- Output: redirect to `/`.
- State: invalidates **DB table `sessions`**, regenerates CSRF token.

### `App\Http\Controllers\Auth\ForgotPasswordController`

**`create()`** — show forgot-password form (`auth.forgot-password`).
**`store(Request $request)`** — validate `email`, send reset link via `Password::broker('users')`.
  - State: reset token → **DB table `password_reset_tokens`** (keyed by email).
  - Email sent via default `ResetPassword` notification (needs `MAIL_*` config to deliver).
**`edit(Request $request, string $token)`** — show reset form (`auth.reset-password`).
**`update(Request $request)`** — verify token + set new password (min 8, confirmed).
  - State: verifies token against **`password_reset_tokens`**, then clears the row.
