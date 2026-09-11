# Feature 13 — Translation / i18n

## 1. Scope

Included:
- Locale switching
- Translation listing
- Translation editing
- Translation creation
- Translation persistence
- Translation fallback
- Cross-page behavior
- Authorization
- Missing translations
- Dynamic translations
- `$title` architecture in app layout

Explicitly out of scope:
- New translation namespaces beyond existing en/id/{ui,messages,validation}.php

## 2. Related QA Cases

- QA-021 locale switch
- QA-068 translation edit
- QA-069 translation add
- QA-101 translation cross-page

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Locale switcher sets session locale; middleware resolves session -> Setting locale_default -> config app.locale.
- Translation management routes under `settings.translations` gated by `feature:translations` + `can:translation.view/edit`.
- Translation controller handles index/edit/update.
- Tests confirm locale switching and translation editing.
- Known issue: QA-069 translation add currently has no Add button.
- `app.blade.php` uses `$title ?? config('app.name', 'Laravel')` for page title.

## 4. Current Implementation

### Middleware
- `SetLocale` resolves locale with fallback chain.

### Routes/Controllers
- `TranslationController` manages language lines.
- `LocaleController` updates session locale.

### Views
- Layout uses `$title` variable; some pages pass explicit title (e.g., DashboardController passes `'title' => 'Dashboard'`).

### i18n conventions
- `ui()` = ui.php (UI labels)
- `__('messages.*')` = messages.php (feedback/domains)
- Mixing causes silent fallback bug; documented in project memory.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Locale switch | Session updated | Same | PASS |
| Translation listing | Available to authorized | Same | PASS |
| Translation edit | Updates DB language line | Same | PASS |
|| Translation add | No Add route/view in code | Should have Add | GAP-I18N-001 (confirmed) |
| Fallback when key missing | File -> DB -> English fallback | Same | PASS |
| Cross-page locale | Applies app-wide | Same | PASS |
| Unauthorized translation access | 403 | Same | PASS |
| Feature flag translations off | 404 | Same | PASS |
| `$title` usage | Mixed hardcoded/translation | Inconsistent | GAP |

## 6. Cross-Feature Dependencies

- Settings: default locale stored in settings.
- Auth: locale switcher available to authenticated users.

## 7. Security Assessment

- Translation editing gated by permission; no direct code execution risk.

## 8. Maintainability Assessment

- Dual-source i18n (file + DB) is project-standard; requires discipline to avoid mixing keys.

## 9. UX Assessment

- Missing Add button blocks creating new translations from UI.

## 10. Identified Gaps

### GAP-I18N-001
**Title:** Translation creation not possible from UI
**Severity:** Medium
**Category:** Functional / UX
**Evidence:** `routes/web.php` has no translation create route; `TranslationController` has only `index/edit/update`; `settings/translations/index.blade.php` has no Add button.
**Current behavior:** Only existing language lines can be edited; new keys cannot be created from UI.
**Expected behavior:** Add create route/view/form, or explicitly document that translations are code-only.
**Likely root cause:** Feature shipped as edit-only.
**Affected components:** `TranslationController`, routes, translations views.
**Related QA cases:** QA-069.

### GAP-I18N-002
**Title:** Page title convention is inconsistent
**Severity:** Low
**Category:** Consistency / Maintainability
**Evidence:** `layouts/app.blade.php` uses `$title ?? config('app.name')`; `DashboardController` passes hardcoded `'Dashboard'`; translation edit view sets `@section('title', 'Translations')`; plan form uses `ui('edit_plan')`/`ui('new_plan')`.
**Current behavior:** Title source varies by page (hardcoded English, ui keys, config fallback).
**Expected behavior:** Standardize on one convention, e.g. `ui('page_*')` keys for every page or controller-passed translatable titles.
**Likely root cause:** Mixed legacy AdminLTE pattern + ad-hoc page titles.
**Affected components:** `DashboardController`, plan/translation views, layout.
**Related QA cases:** QA-101.

## 11. Recommended Direction

- Add translation create route/view.
- Standardize page title source (ui keys vs controller-passed titles).

## 12. Deferred / Open Questions

- Should translation creation allow choosing namespace dynamically?
