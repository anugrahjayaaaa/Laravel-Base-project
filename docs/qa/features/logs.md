# Feature 12 — Logs

## 1. Scope

Included:
- Logs page
- Authorization
- Feature flag behavior
- Pagination
- Filtering/search
- Sensitive data exposure
- Performance

Explicitly out of scope:
- External log aggregation
- Detailed log retention policy

## 2. Related QA Cases

- QA-064 logs page authorized
- QA-065 logs page unauthorized/off

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Log viewer route gated by `feature:logs` + `can:logs.view`.
- Controller is `LogViewerController`; implementation not inspected in this pass beyond route.
- Tests assert authorized access and unauthorized/off behavior.

## 4. Current Implementation

### Routes/Controllers
- `Route::get('/logs', [LogViewerController::class, 'index'])->middleware(['feature:logs', 'can:logs.view'])`

### Tests
- `LogViewerTest` covers authorized/unauthorized/feature-off scenarios.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Authorized access | 200 | Same | PASS |
| Unauthorized | 403 | Same | PASS |
| Feature off | 404 | Same | PASS |
|| Search capability | Level filter only; no text/date search in UI or backend | Should support message/date search | GAP-LOGS-001 |
|| Pagination | LaravelLogViewer `all()` returns full list; no app-level pagination | Should paginate or lazy-load | GAP-LOGS-001 |

## 6. Cross-Feature Dependencies

- Feature flags: `logs` flag controls access.
- Auth: requires `logs.view` permission.

## 7. Security Assessment

- Route gated by permission + feature; direct URL protected.
- Risk of exposing sensitive application logs to privileged users; acceptable for admin tooling.

## 8. Maintainability Assessment

- Minimal controller; if it wraps a log viewer package, verify it is maintained.

## 9. UX Assessment

- Search bar requested but not implemented per docs.

## 10. Identified Gaps

### GAP-LOGS-001
**Title:** Log viewer lacks search/pagination and exposes raw application logs
**Severity:** Medium
**Category:** Security / UX / Performance
**Evidence:** `LogViewerController::index()` uses `LaravelLogViewer::all()` with optional `level` filter only. No message search, no date filter, no pagination. Authz is `feature:logs` + `can:logs.view`.
**Current behavior:** Any privileged user can read all application log files through one endpoint.
**Expected behavior:** Add search/filter by message/date/file, and paginate. Consider log access as sensitive and audit it.
**Likely root cause:** Minimal admin utility shipped without operational hardening.
**Affected components:** `LogViewerController`, `monitoring/logs/index.blade.php`, `Rap2hpoutre\LaravelLogViewer`.
**Related QA cases:** QA-064, QA-065.

## 11. Recommended Direction

- Implement search in log viewer UI with backend filtering.

## 12. Deferred / Open Questions

- Should logs be exportable?
