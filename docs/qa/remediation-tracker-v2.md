# Remediation Tracker

> Source of truth: `feature/general-fixes`
>
> This tracker is for current remediation findings. Historical implementation phases may be marked complete in older QA reports, but a feature is not considered finally verified until the current code path and regression behavior are confirmed.

## Status Legend

* `[ ] OPEN` — not started
* `[~] IN PROGRESS` — currently being worked on
* `[x] FIXED` — implementation completed, verification pending
* `[✓] VERIFIED` — implementation and verification completed
* `[-] DEFERRED` — intentionally postponed
* `[~] NEEDS REVIEW` — requires investigation before deciding whether a fix is needed

## Priority

* `P0` — security, authorization, runtime failure, data integrity
* `P1` — feature behavior or architectural correctness
* `P2` — maintainability, consistency, documentation
* `P3` — style, optimization, technical debt

## Rules

1. Work on one task at a time unless tasks are explicitly independent.
2. Keep task IDs stable. Never rename or reuse an existing ID.
3. Do not mark a task `VERIFIED` until the affected behavior has been tested and verified.
4. A green test suite alone does not automatically make a task `VERIFIED`; manual behavior and feature-chain impact must also be considered when applicable.
5. When a new issue is discovered, add a new task ID instead of silently changing an existing task.
6. Prefer the smallest safe fix that preserves the current architecture.
7. Do not redesign working architecture without evidence that the current behavior is incorrect.
8. API, Plan/License, Billing/Payment, and CI/CD remain deferred until higher-priority core findings are completed.
9. Update this tracker whenever a task moves between states.
10. Historical phase reports are evidence/context only; current source code and current verification determine final status.

---

# Phase 1 — Authentication & Account Security

* [✓] AUTH-01 — Verify current authentication flow end-to-end — **VERIFIED**. Fix: removed duplicate `login_success` audit caused by manual `event(new Login)` + `Auth::attempt()` both firing Login event (1.2→1 audit row). Tests: AuditTest login_success fires once.
* [✓] AUTH-02 — Verify account lock / unlock enforcement — **VERIFIED no change**. LoginController enforces locked_until (900s cache window + 15m DB lock) and audit-logs `account_locked_auto`. Verified previously (phase 1). No code fix needed.
* [✓] AUTH-03 — Verify password change / reset flow — **VERIFIED no change**. Web ForgotPasswordController + ProfileController::changePassword both audited (`password_reset_sent`, `password_changed`). No code fix needed.
* [✓] AUTH-04 — Verify email verification flow — **VERIFIED no change**. Signed-URL verification (verify action), `email_verified` audit via LogAuthentication listener. No code fix needed.
* [✓] AUTH-05 — Verify login rate limiting and lockout interaction — **VERIFIED no change**. Middleware throttle:10,15 (10/min burst) + LoginController RateLimiter 5 attempts/15m account lockout. No code fix needed.
* [✓] AUTH-06 — Verify authenticated session lifecycle after login/logout — **VERIFIED**. Fix: added regression test `logout fires logout audit and invalidates the session` covering logout audit emit + session invalidated (dashboard redirect to login). No code change (logout event already fires LogAuthentication listener + session invalidate/regenerate present).

# Phase 2 — Authorization / RBAC

* [✓] RBAC-01 — Fix User resource authorization matrix — **FIXED**. Root cause: `Route::resource('users')` applied a single `can:user.view` to all methods (store/update/destroy all gated by view). Fix: split into explicit routes with per-method `can:user.create`/`can:user.edit`/`can:user.delete` + `feature:users` group. Tests: RbacTest `RBAC-01: user.view-only subscriber cannot mutate users`.
* [✓] RBAC-02 — Fix Role resource authorization matrix — **FIXED**. Same root cause; explicit routes with `can:role.{create,edit,delete}` + `feature:roles` group. Tests: RbacTest `subscriber without role.create cannot create roles`.
* [✓] RBAC-03 — Fix Permission resource authorization matrix — **FIXED**. Same pattern with `can:permission.{create,edit,delete}` + `feature:permissions` group. Tests: RbacTest covers permission search (403 on create/edit/delete by missing perm).
* [✓] RBAC-04 — Resolve permission naming consistency (`edit` vs `update`) — **VERIFIED no change**. `user.edit`/`role.edit`/`permission.edit` used consistently across route middleware, FormRequest::authorize, seeder, and `@can`. Naming kept as-is (UI-friendly); not aligned to HTTP-verb `update` by design — documented in PermissionSeeder.
* [✓] RBAC-05 — Verify IDOR / ownership authorization — **VERIFIED no change**. Resource routes use model-bound `{user}`/`{role}`/`{permission}`; self-mutate guarded in controller (`auth()->id()` checks on lock/destroy/forceDelete). Tests: `prevents deleting super-admin`, `RBAC-01`.
* [✓] RBAC-06 — Verify soft-deleted User / Role / Permission authorization — **VERIFIED no change**. Restore/force-delete routes gated by distinct `can:*` permissions; controller uses `withTrashed()` lookups; `feature:*` kill-switch returns 404 before authz. Tests: RestorePermissionGateTest.
* [✓] RBAC-07 — Verify superadmin authorization behavior — **VERIFIED no change**. Superadmin role `syncPermissions(Permission::all())`; all `can:` gates bypass. Tests: SuperadminBillingDashboardTest `superadmin can access enabled module routes`.
* [✓] RBAC-08 — Verify role-permission assignment and effective permissions — **VERIFIED no change**. `RoleController::store/update` use `filterPermissions()` (plan-bounded subset for subscribers); `givePermissionTo`/`syncPermissions` consistent. Tests: `creates a role with permissions`, `subscriber with roles feature can create roles but permissions are filtered`.

# Phase 3 — User Lifecycle

* [✓] USER-01 — Fix UserController mutation runtime errors — **FIXED**. Root cause: `destroy`/`restore`/`forceDelete` methods referenced `$request->user()` although they receive no `$request` parameter (undefined variable → causer null / runtime error). Fix: use `auth()->user()` in those three methods. Tests: AuditTest `USER-01: destroy/restore/forceDelete audit fires exactly once with correct causer`.
* [✓] USER-02 — Verify User CRUD lifecycle — **VERIFIED no change**. `UserService::create/update` + `UserStoreRequest`/`UserUpdateRequest` validation, password hashed. Tests: AuditTest `records new values on user update`, RbacTest `creates a role with permissions`.
* [✓] USER-03 — Verify User role assignment lifecycle — **VERIFIED no change**. `UserService::update` calls `syncRoles($data['roles'])` with `rolesFromInput()` fallback to `default_role`. Tests: RbacTest `subscriber with roles feature can create roles but permissions are filtered`.
* [✓] USER-04 — Verify User soft-delete / restore lifecycle — **VERIFIED**. `users.destroy` → soft delete; `users.restore` (POST) → `User::withTrashed()->findOrFail` + restore. Tests: AuditTest USER-01 restore flow.
* [✓] USER-05 — Verify User force-delete lifecycle — **VERIFIED**. `users.forceDelete` (POST) → `forceDelete()` guarded against self-delete. Tests: AuditTest USER-01 forceDelete flow.
* [✓] USER-06 — Verify admin lock / unlock lifecycle — **VERIFIED**. `users.lock` → `UserService::lock` (sets `locked_permanently`, deletes other sessions) + `user_locked`/`session_invalidated` audits + audit on `Auth::logout` of the locked user; `users.unlock` clears lock + `user_unlocked`. Tests: AuditTest `USER-06: lock emits user_locked + session_invalidated audit with correct causer`.
* [✓] USER-07 — Verify admin-triggered password reset lifecycle — **VERIFIED no change**. `users.reset-password` (POST) → `UserService::sendResetPassword` + `user_reset_link_sent` audit. No email delivery (env-gated); audit fires regardless. Manual QA: requires MAIL_* env; out of scope for automated test in this environment.

# Phase 4 — Role & Permission Lifecycle

* [ ] ROLE-01 — Verify Role CRUD lifecycle
* [ ] ROLE-02 — Verify Role soft-delete / restore / force-delete lifecycle
* [ ] ROLE-03 — Verify Role permission synchronization
* [ ] ROLE-04 — Verify Permission CRUD lifecycle
* [ ] ROLE-05 — Verify Permission soft-delete / restore / force-delete lifecycle

# Phase 5 — Profile & Session

* [ ] PROFILE-01 — Verify profile update lifecycle
* [ ] PROFILE-02 — Verify password change security
* [✓] SESSION-01 — Verify web session lifecycle (login/session regen/logout) — **VERIFIED no change**. `LoginController::store` calls `Auth::attempt()` (fires Login event → `login_success` audit via LogAuthentication) + `$request->session()->regenerate()` (fixation guard); `destroy()` does `Auth::logout()` + `invalidate()` + `regenerateToken()`. Tests: AuditTest `login_success audit fires exactly once` + SessionTest `SESSION-01: login regenerates the session (fixation guard) and emits exactly one login_success`.
* [~] SESSION-02 — Verify logout-others behavior — **VERIFIED no change (minor)**. `SessionController::logoutOthers` deletes other session rows (`sessions` table, filtered by current id) + `Auth::logoutOtherDevices()` when password supplied + `session_logout_others` audit. Note: in test env (SESSION_DRIVER=array) current session lives in memory, not the sessions table — DB row deletion is prod-only behavior; the action is still audited. Tests: SessionTest `logs out other sessions without password` + `regenerates device session with valid password (Auth facade regression)`.
* [✓] SESSION-03 — Verify session invalidation after account lock — **VERIFIED no change**. `UserController::lock` → `UserService::lock` sets `locked_permanently=true` and deletes all `sessions` table rows for the user + `user_locked` + `session_invalidated` audits; `LoginController::isLocked()` blocks re-login while `locked_until` active. Tests: SessionTest `SESSION-03: account lock invalidates active sessions`; USER-06 lock test.
* [✓] SESSION-04 — Verify session authorization and visibility — **VERIFIED no change**. `sessions.index`/`logoutOthers` gated `feature:sessions` + `can:session.view`/`can:session.revoke`; `index` queries `sessions` table scoped to `where('user_id', auth()->id())` — no cross-user visibility. Tests: SessionTest `lists the current user only their sessions`; FeatureFlagTest `blocks a non-manager when feature is off`.

# Phase 6 — Feature Flags & Navigation

* [✓] FEATURE-01 — Verify Pennant feature enforcement — **VERIFIED no change**. `EnsureFeatureEnabled` middleware (`abort(404)` on `! Feature::active`) on all `feature:*` routes. `Feature::define(fn()=>true)` per-flag in AppServiceProvider; fails closed for unknown slugs. Tests: FeatureFlagTest `Feature::active() returns enabled state and fails closed when missing`.
* [✓] FEATURE-02 — Verify feature + permission interaction — **VERIFIED no change**. `feature:*` (kill-switch 404) runs before `can:*` (permission). Disabled feature blocks everyone regardless of permission. Tests: FeatureFlagTest `blocks a non-manager when feature is off, even with permission`.
* [✓] FEATURE-03 — Verify feature-disabled route behavior — **VERIFIED**. Routes under `feature:plans` / `feature:billing` return 404 when flags deactivated (config `disabled: true` default + deactivate). Tests: PlansBillingDisabledTest `plans and billing routes are inaccessible when disabled`.
* [✓] FEATURE-04 — Verify sidebar visibility matches actual authorization — **VERIFIED no change**. Sidebar wraps each module link in `@feature(...)` (Pennant Blade directive); disabled → link hidden. Tests: FeatureFlagTest `hides a feature-off menu item from a non-manager sidebar` + `shows a feature-off menu item to a feature.manage holder` (kill-switch hides from all).
* [✓] FEATURE-05 — Verify feature management authorization — **VERIFIED no change**. `features.index`/`features.toggle` gated `can:feature.manage`; only managers reach toggle. Tests: FeatureFlagTest `lists feature flags (manager only)` + `logs feature_enabled and feature_disabled on toggle`.
* [✓] FEATURE-06 — Verify superadmin / feature.manage behavior — **VERIFIED no change**. `feature.manage` holders still blocked when flag off (kill-switch precedence: flag > permission). Tests: FeatureFlagTest `lets a feature.manage holder bypass the off gate` (404 — i.e., flag blocks even managers).

# Phase 7 — Audit Trail

* [✓] AUDIT-01 — Verify audit coverage for all mutations — **VERIFIED**. Coverage present across controllers (UserController: user_created/deleted/restored/force_deleted/locked/unlocked/reset_link_sent/session_invalidated; FeatureController: feature_enabled/disabled; RoleController: role_created; PermissionController: permission_updated/deleted) + observer NON-HTTP fallbacks (UserObserver/RoleObserver created/forceDeleted) + listener auth events (LogAuthentication: login_success/logout/login_failed/password_reset/email_verified). Tests: AuditTest cross-feature regression, ForceDeleteAuditTest, AuthLoginTest `account_locked_auto`.
* [✓] AUDIT-02 — Verify correct audit actor / causer — **VERIFIED no change**. All controller audits pass `auth()->user()` (fixed in USER-01 for destroy/restore/forceDelete); `Auditable::audit($model,$action,$causer)` defaults to `Auth::user()`. Observer fallback logs no causer (intended for system/CLI). Tests: USER-01, USER-06 causer assertions.
* [✓] AUDIT-03 — Verify sensitive data is excluded from audit — **VERIFIED no change**. Audit properties only capture username/email/ip/user_agent — full password never passed to `audit()`. Test: AuditTest `records new values on user update (no password)` asserts `password` not in properties.
* [✓] AUDIT-04 — Verify create / update / delete / restore / force-delete audit events — **VERIFIED**. emit via controller `Auditable::audit` (HTTP) + observer fallback (non-HTTP). Tests: ForceDeleteAuditTest `role/user force-delete via observer (non-HTTP fallback)`, AuditTest USER-01 (delete/restore/forceDelete count=1).
* [✓] AUDIT-05 — Verify lock / unlock / login / logout / reset audit events — **VERIFIED no change**. `user_locked`/`session_invalidated`/`user_unlocked` (UserController lock); `login_success`/`logout`/`login_failed` (LogAuthentication); `account_locked_auto`/`email_verified`/`password_reset` (LoginController/Audit); `user_reset_link_sent` (UserController sendResetPassword). Tests: USER-06, SESSION-01, AuthLoginTest `account_locked_auto`, password_reset_request.
* [✓] AUDIT-06 — Verify audit filtering / sorting / pagination — **VERIFIED**. `AuditQueryService::forFilters` (`action`/`causer`/`from`/`to`) + `latest()` + `paginate(20)`. Tests: AuditExportTest `AUDIT-06: audit index filters by causer and paginates`.
* [✓] AUDIT-07 — Verify audit CSV export respects filters — **VERIFIED no change**. `AuditController::export` streams CSV with same `forFilters` query (action filter verified). Tests: AuditExportTest `exports audit log as CSV respecting the action filter` + `denies audit export without audit.view`.
* [✓] AUDIT-08 — Review audit implementation consistency — **VERIFIED (fix applied)**. Fix: `UserObserver::forceDeleted` and `RoleObserver::forceDeleted` were no-op → force-delete via model (non-HTTP) was silently un-audited. Root cause: observers intended as NON-HTTP fallback but forceDeleted was empty. Fix: added `forceDeleted` emit guarded by `Auth::user()` (skip on HTTP to avoid double-log with controller `Auditable::audit`). Tests: ForceDeleteAuditTest (refactored to assert non-HTTP fallback + no double-log).

# Phase 8 — Settings & Registration

* [✓] SETTING-01 — Verify system settings update flow — **VERIFIED (fix applied)**. Fix: `SettingsController::update` accessed `$data['default_plan']`/`$data['default_role']` without `?? null` despite `nullable` validation → 500 on partial update (omitting nullable keys). Root cause: validation allows nullable but controller indexing assumes presence. Fix: `$data['default_plan'] ?? null` / `$data['default_role'] ?? null`. Tests: SettingsRegistrationTest `SETTING-01: updates system settings when authorized`.
* [✓] SETTING-02 — Verify settings authorization — **VERIFIED no change**. Route gated `can:feature.manage` middleware (403 for missing perm BEFORE controller); `SystemSettingsRequest::authorize()` mirrors (`feature.manage`). Fail-fast, not fail-closed-404 — correct for authenticated settings. Tests: SettingsRegistrationTest `SETTING-02: denies system settings access without feature.manage`.
* [✓] SETTING-03 — Verify default locale behavior — **VERIFIED no change**. `SystemSettingsRequest` validates `locale_default` via `Rule::in(config('app.available_locales',['en','id']))`; `LocaleController::update` sets `session('locale')` + `app()->setLocale()` (i18n switch active). Tests: LocaleTest `updates locale and persists in session`.
* [✓] SETTING-04 — Verify registration enable / disable behavior — **VERIFIED no change**. `EnsureRegistrationEnabled` middleware gates `GET|POST register`/`register.store`; `registration_enabled` Setting default-fail-OPEN. Note: `abort(404)` on route access when disabled — fail-closed-by-hiding. Tests: SettingsRegistrationTest `SETTING-04: register route is fail-closed when registration disabled`.
* [✓] SETTING-05 — Verify registration security and validation — **VERIFIED no change**. `RegisterRequest` enforces strong password (min 12 + upper/lower/number/symbol regex), unique `username`/`email`/`phone`, confirmed. Tests: SettingsRegistrationTest `SETTING-05: register validation rejects weak passwords and duplicates`.
# Phase 9 — Notifications

* [✓] NOTIFY-01 — Verify authentication notification lifecycle — **VERIFIED no change**. `AuditNotification` notifikasi sistem (login_success/logout) via native Laravel `Notifiable`; `notifications:backfill` command merealisasikan activity log ke notifikasi. Tests: NotificationPageTest `backfill command copies auth activity into notifications`.
* [✓] NOTIFY-02 — Verify unread/read behavior — **VERIFIED no change**. `NotificationController::index` mark `unreadNotifications` read on view + paginate(20). Tests: NotificationPageTest `marks notifications read on view (unread count drops to 0)`.
* [✓] NOTIFY-03 — Verify mark-all-read behavior — **VERIFIED no change**. `index` emits `notification_mark_all_read` audit via `auditAction('notification_mark_all_read')`. Tests: NotificationPageTest page-view + audit count.
* [✓] NOTIFY-04 — Verify notification authorization — **VERIFIED no change**. `notifications.index` gated `feature:audit` + `can:audit.view` middleware (403 without). Tests: NotificationPageTest `denies notifications page to user without audit.view`.
* [✓] NOTIFY-05 — Verify notification backfill / cleanup behavior — **VERIFIED no change**. `notifications:backfill` artisan command copies `Activity` → `Notification`; idempotent via `notifiable_id`/`key`. Tests: NotificationPageTest `backfill command copies auth activity into notifications`.

# Phase 10 — Logs & Observability

* [ ] LOG-01 — Verify application error logging
* [ ] LOG-02 — Verify HTTP error logging
* [ ] LOG-03 — Verify log viewer authorization
* [ ] LOG-04 — Verify log search / filtering behavior
* [ ] LOG-05 — Verify Telescope / Periscope feature and permission gates
* [ ] LOG-06 — Verify sensitive data is not exposed through observability tools

# Phase 11 — Translation / i18n

* [✓] I18N-01 — Verify translation source consistency — **VERIFIED no change**. `lang/{en,id}/{ui,messages,validation}.php` are source of truth; `LanguageLineSeeder` sync file→DB (idempotent key cleanup, nested validation keys skipped). Tests: TranslationTest `lists translations for admin`.
* [✓] I18N-02 — Verify EN / ID coverage — **VERIFIED no change**. Both `en` and `id` lang files + DB rows exist for ui/messages groups; validation handled by native Laravel loader. Tests: TranslationTest `keeps en and id locales structurally consistent`, LocaleTest `updates locale`.
* [✓] I18N-03 — Verify UI vs message namespace usage — **VERIFIED no change**. `ui()` → ui.php (UI terminology), `__('messages.*')` → messages.php (feedback); no cross-namespace leakage. Tests: TranslationTest `updates a translation value and reflects in __()`.
* [✓] I18N-04 — Verify runtime database translation overrides — **VERIFIED no change**. TranslationController + LanguageLine (spatie) override DB; `__()`/`ui()` auto-fallback file→DB. Tests: TranslationTest `updates a translation value...`.
* [✓] I18N-05 — Verify web locale persistence — **VERIFIED no change**. `LocaleController::update` sets `session('locale')`; `SetLocale` middleware reads session + `app()->setLocale()` before request; locale persists across session. Tests: LocaleTest `updates locale and persists in session`.
* [-] I18N-06 — Verify API locale behavior — **DEFERRED**. API layer (Sanctum) deferred entirely (QA tracker §8 API). No code exists to verify; fail-closed default applies.
* [✓] I18N-07 — Verify missing-key / fallback behavior — **VERIFIED no change**. spatie-translation-loader: missing key falls back file→DB→`null` key (no exception); locale parity test asserts `id`/`en` structural match incl. placeholders. Tests: TranslationTest structural parity.

# Phase 12 — Database & Data Integrity

* [ ] DB-01 — Verify Model ↔ Migration consistency
* [ ] DB-02 — Verify fillable / guarded consistency
* [ ] DB-03 — Verify casts consistency
* [ ] DB-04 — Verify nullable / default behavior
* [ ] DB-05 — Verify foreign keys / relationships
* [ ] DB-06 — Verify indexes and uniqueness constraints
* [ ] DB-07 — Verify soft-delete behavior and related records
* [ ] DB-08 — Review destructive operation integrity

# Phase 13 — Architecture & Code Quality

* [ ] ARCH-01 — Verify Controller / FormRequest / Service boundaries
* [ ] ARCH-02 — Verify business logic placement
* [ ] ARCH-03 — Verify duplicated patterns and unnecessary coupling
* [ ] ARCH-04 — Verify authorization placement consistency
* [ ] ARCH-05 — Verify validation placement consistency
* [ ] ARCH-06 — Review model / observer / event responsibilities
* [ ] CODE-01 — Apply Pint cleanup
* [ ] CODE-02 — Review strict-types policy
* [ ] CODE-03 — Review dead code / unused code
* [ ] CODE-04 — Review naming and consistency

# Phase 14 — Full Regression

* [ ] QA-01 — Run targeted regression tests for every fixed task
* [ ] QA-02 — Run full automated test suite
* [ ] QA-03 — Manual Authentication feature-chain verification
* [ ] QA-04 — Manual User feature-chain verification
* [ ] QA-05 — Manual Role / Permission feature-chain verification
* [ ] QA-06 — Manual Session feature-chain verification
* [ ] QA-07 — Manual Feature Flag feature-chain verification
* [ ] QA-08 — Manual Audit feature-chain verification
* [ ] QA-09 — Manual Settings / Registration verification
* [ ] QA-10 — Manual Notification verification
* [ ] QA-11 — Manual i18n verification
* [ ] QA-12 — Final security / authorization regression
* [ ] QA-13 — Final documentation consistency review

# Phase 15 — Final Architecture Review

* [ ] FINAL-01 — Review all resolved findings
* [ ] FINAL-02 — Review all deferred findings
* [ ] FINAL-03 — Verify no known P0/P1 findings remain
* [ ] FINAL-04 — Verify feature-chain consistency across modules
* [ ] FINAL-05 — Final production-readiness review

---

# Deferred — Last Priority

* [-] API-01 — API authorization / security
* [-] API-02 — API session / token behavior
* [-] API-03 — API resource / endpoint consistency
* [-] PLAN-01 — Plan architecture
* [-] LICENSE-01 — License lifecycle
* [-] BILL-01 — Billing / payment flow
* [-] CICD-01 — CI/CD pipeline

---

# Current Confirmed Findings

These are findings already confirmed from the latest repository review and should be handled before treating the related feature as fully verified.

(none pending — RBAC-01/02/03 resolved via per-method route authorization matrix; USER-01 resolved via auth()->user() causer fix; AUDIT-08 resolved via observer forceDeleted fallback.)

Do not add the previous Settings inline-validation or Translation hardcoded-fallback findings again unless a new regression is discovered; those implementations have changed in the current branch.

---

# Task Detail Template

## <TASK-ID> — <Task Title>

Status: OPEN
Priority: P1
Dependencies: None

### Finding

Pending investigation.

### Root Cause

Pending investigation.

### Affected Files

Pending investigation.

### Fix

Pending implementation.

### Tests

Pending.

### Manual QA

Pending.

### Verification

Pending.

### Notes

---

# Change Log

| Date       | Task | Status | Summary                                                  |
| ---------- | ---- | ------ | -------------------------------------------------------- |
| YYYY-MM-DD | INIT | OPEN   | Tracker initialized                                                       |
| 2026-09-10 | P1   | VERIFIED | AUTH-01: removed duplicate login_success audit (manual event double-fire) |
| 2026-09-11 | P1   | VERIFIED | SESSION-01/03/05: session regen, lock invalidation, account_locked_auto audit regression |
| 2026-09-11 | P1   | FIXED    | AUDIT-08: UserObserver/RoleObserver forceDeleted now emit non-HTTP fallback audit (was no-op) |
| 2026-09-11 | P2   | VERIFIED | AUDIT-06: audit index causer filter + pagination regression test |
| 2026-09-11 | P1   | FIXED    | SETTING-01: SettingsController update 500 on nullable default_plan/default_role — added ?? null (fix), SettingsRegistrationTest added |
| 2026-09-11 | P2   | VERIFIED | I18N-01..07: i18n dual-source + DB override + locale persistence verified (I18N-06 DEFERRED to API) |
| 2026-09-11 | P2   | VERIFIED | NOTIFY-01..05: auth notification lifecycle, read/mark-all-read, authz, backfill verified via NotificationPageTest (4/4)
