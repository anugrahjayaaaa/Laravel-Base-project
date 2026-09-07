# Phase 1 — Authentication Core

## Completed
- Stabilized authentication contract for web + API login paths.
- Enforced account-lock behavior in API authentication.
- Fixed i18n violation on login password toggle.
- Updated QA docs to reflect verified behavior.
- Verified with automated tests; no regressions in targeted suite.

## Changes Made
- `app/Http/Controllers/Api/AuthController.php`: enforce `isLocked()` before password check.
- `tests/Feature/ApiAuthTest.php`: add locked-account rejection test.
- `resources/views/auth/login.blade.php`: replace hardcoded aria-label with `__('ui.show_password')` / `__('ui.hide_password')`.
- `lang/en/ui.php`: add `hide_password`.
- `lang/id/ui.php`: add `hide_password`.
- `docs/qa/features/authentication.md`: mark API login/lockout as confirmed behavior; add `GAP-AUTH-005`.
- `docs/qa/features/password-recovery.md`: correct password policy docs; reclassify stale doc gap.
- `docs/qa/features/email-verification.md`: no behavior change; docs remain accurate.

## Root Causes Addressed
- API auth bypassed account lock because it duplicated login logic without `isLocked()`.
- Login toggle aria-label used inline English instead of project i18n helper.

## Tests
- `php artisan test --filter="AuthLoginTest|ApiAuthTest|ApiEndpointsTest|SessionTest|FeatureFlagTest|SidebarVisibilityTest|PlanPermissionBoundaryRuntimeTest|RbacTest|LogViewerTest|AuditTest|ForceDeleteAuditTest|LocaleTest|TranslationTest|ArchitectureAuditTest"`
- Result: 137 passed, 1418 assertions.

## Manual QA
- Verified API locked-account path via new test scenario.
- Verified login view renders translated aria-label keys.

## Security Verification
- Locked accounts now fail closed on API login.
- Authentication error messages remain generic; no user enumeration.
- Password reset token remains single-use via broker; no regression.

## Regressions Checked
- No changes to web login flow beyond view text.
- No changes to password reset, session logout, or feature flag behavior.
- Existing API happy-path tests still pass.

## Remaining Gaps
- `GAP-AUTH-003`: locked-user UI still shows only inline validation message; no dedicated lock state block.
- `GAP-AUTH-002`: logout others still optional password.
- `GAP-AUTH-005` resolved by enforcement; residual design question remains whether API should also apply `locked_permanently` distinct messaging.

## Design Decisions Required
- None forced in Phase 1.

## Files Changed
- `app/Http/Controllers/Api/AuthController.php`
- `tests/Feature/ApiAuthTest.php`
- `resources/views/auth/login.blade.php`
- `lang/en/ui.php`
- `lang/id/ui.php`
- `docs/qa/features/authentication.md`
- `docs/qa/features/password-recovery.md`

## Commit Recommendation
```
fix(auth): enforce account lock in API login; fix login toggle i18n; update QA docs
```

## Ready for Next Phase
YES
