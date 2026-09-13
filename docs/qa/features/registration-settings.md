# Feature 08 — Registration & Application Settings

## 1. Scope

Included:
- Registration enabled/disabled toggle
- Registration route behavior
- Settings page
- Registration toggle behavior
- Direct URL behavior when disabled
- Authorization for settings

Explicitly out of scope:
- Plan/billing interaction with default role/plan
- Detailed settings page feature matrix beyond registration/locale

## 2. Related QA Cases

- QA-014 registration enabled
- QA-015 registration disabled 404
- QA-066 settings page
- QA-067 registration toggle
- QA-070 settings direct 403
- QA-100 registration setting

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Registration routes gated by `RegistrationEnabled` middleware; returns 404 when `Setting::get('registration_enabled')` is falsy.
- Settings page gated by `can:feature.manage` on route middleware.
- Settings page shows default locale, registration enabled, license mode, default plan, default role.
- System settings update writes to `Setting` model via `Setting::set()`.
- Feature flags can be toggled from `/features` by `feature.manage` holders.
- Default locale fallback chain: session -> `Setting::get('locale_default')` -> `config('app.locale')`.

## 4. Current Implementation

### Controllers
- `RegisterController::show/store` for registration.
- `SettingsController::index/update` for system settings.

### Middleware
- `RegistrationEnabled` returns 404 when disabled.

### Routes
- Registration routes: `guest` + `registration.enabled` + throttle.
- Settings routes: `can:feature.manage`.

### Tests
- QA-014/015/066/067/070 are marked passed in tracker; implementation confirms expected behavior.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Registration enabled | Routes accessible + form shown | Same | PASS |
| Registration disabled | 404 on register routes | Same | PASS |
| Direct URL /register when disabled | 404 | Same | PASS |
| Settings page access without feature.manage | 403 | Same | PASS |
| Update default locale | Setting updated | Same | PASS |
| Update registration toggle | Setting updated + immediate effect | Same | PASS |
| Update license mode | Setting updated | Deferred to plan/billing | PASS |

## 6. Cross-Feature Dependencies

- Registration default role uses `Setting::get('default_role')`.
- Locale affects entire app via `SetLocale` middleware.
- Feature flags affect navigation/routes independently.

## 7. Security Assessment

- Fail-closed registration disabled (404 not 403).
- Settings page protected by permission gate.

## 8. Maintainability Assessment

- `Setting::get/set` is single point for system settings.
- No caching observed; could cause repeated DB reads per request.

## 9. UX Assessment

- Settings toggle uses checkbox/boolean inputs; acceptable.
- No visible explanation of what each setting does beyond labels.

## 10. Identified Gaps

### GAP-SETTINGS-001
**Title:** Repeated uncached `Setting::get()` reads
**Severity:** Low
**Category:** Performance / Maintainability
**Evidence:** `SetLocale`, `RegistrationEnabled`, `SettingsController`, and multiple views read settings directly from DB per request.
**Current behavior:** Multiple DB reads per request for static-ish settings.
**Expected behavior:** Cache settings and invalidate on update.
**Likely root cause:** Optimization not implemented.
**Affected components:** `Setting` model/usages.
**Related QA cases:** None.

## 11. Recommended Direction

- Cache settings reads; invalidate on update.

## 12. Deferred / Open Questions

- Should license mode changes require restart or propagate live?
