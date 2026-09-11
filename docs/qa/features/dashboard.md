# Feature 15 — Dashboard

## 1. Scope

Included:
- Admin dashboard
- Limited-user dashboard
- Feature-off behavior
- Permission behavior
- Data visibility
- Empty states

Explicitly out of scope:
- Plan/billing-specific dashboard widgets
- Detailed analytics

## 2. Related QA Cases

- QA-075 dashboard admin
- QA-076 dashboard limited
- QA-077 dashboard feature-off safety

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Dashboard shows user count, role count, audit count, license badge, welcome message.
- Dashboard controller computes license status/days left/active plan.
- Route gated by `auth` middleware only; no explicit feature gate in route definition.
- Tests confirm admin sees full dashboard; limited users see subset; feature-off behavior is tested.

## 4. Current Implementation

### Controllers
- `DashboardController::index` aggregates counts + license info.

### Views
- `dashboard.blade.php` shows KPI cards + license badge + welcome text.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Admin dashboard | All KPIs shown | Same | PASS |
| Limited user dashboard | Subset shown | Same | PASS |
|| Feature off for dashboard module | Route accessible; no feature middleware | Product decision: core vs module | DESIGN QUESTION |
| Unauthorized widget action | No actions shown | Same | PASS |
| Empty state for zero counts | Shows 0 | Acceptable | PASS |

## 6. Cross-Feature Dependencies

- License/plan subsystem affects dashboard badge.
- Audit count depends on activity log.

## 7. Security Assessment

- No sensitive data exposed beyond counts.
- Dashboard is authenticated but not explicitly feature-gated.

## 8. Maintainability Assessment

- Simple controller + view; low maintenance burden.

## 9. UX Assessment

- Clear KPI cards; license warning present.

## 10. Identified Gaps

### GAP-DASH-001
**Title:** Dashboard route lacks explicit feature gate
**Severity:** Low
**Category:** Consistency / Feature flags
**Evidence:** `routes/web.php` line 55 defines `/dashboard` with `auth` only; no `feature:` middleware.
**Current behavior:** Dashboard accessible even if a related feature flag is turned off.
**Expected behavior:** Decide whether dashboard is core (always on) or module-gated.
**Likely root cause:** Dashboard treated as core landing page, not a togglable module.
**Affected components:** `routes/web.php`, `DashboardController`.
**Related QA cases:** QA-077.

## 11. Recommended Direction

- Decide whether dashboard is core or module; gate if needed.

## 12. Deferred / Open Questions

- Should dashboard show plan-limited KPIs differently?
