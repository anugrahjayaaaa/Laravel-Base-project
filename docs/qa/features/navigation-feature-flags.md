# Feature 04 — Navigation / Sidebar / Feature Flags

## 1. Scope

Included:
- Sidebar visibility behavior
- Permission-based navigation
- Feature-based navigation
- Feature + permission interaction
- Hidden routes when feature off
- Direct URL behavior when feature off / no permission
- Feature toggle behavior
- Route 404 vs 403 behavior
- Feature management UI authorization

Explicitly out of scope:
- Plan/Billing feature entitlement boundary
- License mode behavior
- Dashboard content visibility beyond route/sidebar exposure

## 2. Related QA Cases

- QA-024 sidebar visibility
- QA-025 sidebar flag-off behavior
- QA-026 sidebar permission behavior
- QA-035 direct URL no perm
- QA-036 direct URL flag off
- QA-052 users page unauthorized
- QA-053 users page feature off
- QA-070 settings direct 403
- QA-071 toggle flag navigation
- QA-072 toggle flag route 404
- QA-073 toggle flag 403
- QA-074 API flag behavior
- QA-099 flag toggle + access

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Feature flags are managed via Laravel Pennant, stored in DB `features` table.
- Default flag state is ON; toggling changes DB value.
- `EnsureFeatureEnabled` middleware returns 404 when flag inactive.
- Routes stack `feature:{slug}` before `can:{perm}`.
- Sidebar visibility is computed in `partials/layout/sidebar.blade.php`:
  - Parent shown if ANY child route is both permission-allowed AND feature-active.
  - Child items wrapped with `@can` and `@feature`.
- When flag is off:
  - Route returns 404 for everyone, including managers.
  - Sidebar item hidden for everyone, including managers.
- Feature management page `/features` is gated by `can:feature.manage` only.
- Direct URL behavior:
  - No permission + flag on -> 403
  - Flag off -> 404
- API feature behavior is partial: `/api/v1/features` list is authenticated without explicit `feature.manage` gate in current route definition.

## 4. Current Implementation

### Middleware
- `EnsureFeatureEnabled` returns 404 for inactive flags.
- `RegistrationEnabled` returns 404 when registration disabled.

### Routes
- Web: `feature:` middleware on all module routes.
- API: `features` index is under `auth:sanctum` but not gated by `feature:features` or `can:feature.manage` in `routes/api.php`.
- `FeatureController::toggle` requires `can:feature.manage`.

### Sidebar
- Computed visibility for Access Management and Monitoring parents.
- Settings submenu is always rendered under Settings parent, with individual items gated by `@can`/`@feature`.

### Tests
- `FeatureFlagTest` covers:
  - Flag activate/deactivate state
  - Non-manager blocked when off
  - Manager still blocked when off
  - Route allowed when on
  - Sidebar hidden when off for non-manager and manager
- `SidebarVisibilityTest` covers permission-based child visibility and parent collapse behavior.
- `RestorePermissionGateTest` confirms restore/force-delete permissions for superadmin and staff denial.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Feature flag ON + user has permission | Route 200, sidebar visible | Same | PASS |
| Feature flag OFF + user has permission | Route 404, sidebar hidden | Same | PASS |
| Feature flag OFF + manager | Route 404, sidebar hidden | Same | PASS |
| Direct URL without permission | 403 | Same | PASS |
| Direct URL with flag off | 404 | Same | PASS |
| Sidebar parent collapse when no child visible | Parent hidden | Same | PASS |
| Feature toggle immediate effect | Immediate on next request | Same | PASS |
|| API feature list without manager permission | Returns list | Should require manager | GAP-FEAT-001 (confirmed) |
| API feature toggle without manager permission | 403 from route middleware | Same | PASS |
| Settings menu always visible as parent | Parent shown regardless | Debatable | DESIGN QUESTION |
| Feature flag DB persistence | Stored in `features` table | Same | PASS |

## 6. Cross-Feature Dependencies

- RBAC: sidebar visibility depends on role permissions.
- Plan/Billing: some modules are also gated by plan entitlement at runtime; feature flag is global kill switch above plan.
- User management: feature.manage permission holders see feature management UI.
- Sessions/api-tokens: sidebar visibility depends on `session.view`/`api-token.view` permissions.

## 7. Security Assessment

Strengths:
- Fail-closed feature off behavior (404 for all).
- Route-level enforcement prevents direct URL bypass.
- Managers cannot bypass disabled modules via UI or direct URL.

Concerns:
- API `/features` index lacks explicit `feature.manage` route middleware; any authenticated user can list flags.
- Feature toggle state is not audited in reviewed code.

## 8. Maintainability Assessment

- `EnsureFeatureEnabled` is reusable and minimal.
- Sidebar visibility logic is inline Blade; acceptable but can drift as menu grows.
- Feature declarations centralized in `config/pennant.php`; good.

## 9. UX Assessment

- Sidebar parent collapse behavior is consistent.
- Feature management page provides simple toggle UX.
- No indication of why a menu item is missing (flag off vs permission missing).

## 10. Identified Gaps

### GAP-FEAT-001
**Title:** API feature list exposes feature flags to any authenticated user
**Severity:** Medium
**Category:** Authorization / API
**Evidence:** `routes/api.php` line 53 defines `Route::get('features', ...)->middleware('auth:sanctum')` without `can:feature.manage`. Runtime test confirms admin token returns list without manager gate.
**Current behavior:** Any valid API token holder can enumerate feature flags.
**Expected behavior:** Feature management endpoints should require `feature.manage`.
**Impact:** Information disclosure of module enablement state.
**Likely root cause:** Missing route middleware.
**Affected components:** `routes/api.php`, `FeatureApiController::index`.
**Related QA cases:** QA-074, QA-099.

### GAP-FEAT-002
**Title:** No audit trail for feature flag toggles
**Severity:** Low
**Category:** Audit / Maintainability
**Evidence:** `FeatureController::toggle()` does not log activity.
**Current behavior:** Toggle actions are not recorded in audit log.
**Expected behavior:** Each toggle should produce `feature_enabled`/`feature_disabled` activity entries.
**Impact:** Cannot trace who enabled/disabled a module.
**Likely root cause:** Missing activity call in controller.
**Affected components:** `FeatureController::toggle`.
**Related QA cases:** QA-060.

## 11. Recommended Direction

- Add `can:feature.manage` middleware to API feature routes.
- Add audit logging to feature toggle action.

## 12. Deferred / Open Questions

- Should the Settings parent menu be hidden when no child feature/permission is available to the current user?
