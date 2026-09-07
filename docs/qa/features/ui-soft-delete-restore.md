# Feature 14 — UI / Soft Delete / Restore Consistency

## 1. Scope

Included:
- Restore icons
- Deleted messages
- Soft-delete indicators
- Restore actions
- Force-delete actions
- Bulk actions
- Confirmation dialogs
- Status labels
- Consistency between pages

Explicitly out of scope:
- Plan/billing UI consistency
- Notification UI consistency

## 2. Related QA Cases

- QA-031 delete in-use role
- QA-040 restore/force delete permission
- QA-045 soft delete user
- QA-046 restore user
- QA-047 force delete user
- QA-048 bulk delete/restore users

## 3. Current Runtime Behavior

### Observed (from code + limited view inspection)
- Users/roles/permissions index pages use `withTrashed()` and likely show deleted entries.
- Bulk actions support soft/force delete.
- Restore and force-delete routes exist for users/roles/permissions.
- Flash messages exist for delete/restore/force-delete.
- Specific UI inconsistencies noted in issue tracker:
  - `features` page shows `ui.feature_group_settings`
  - restore UI should use `ui.restore` on restore icon
  - deleted messages inconsistent across soft-deleted resources

## 4. Current Implementation

### Controllers
- Standard REST + bulk + restore/force-delete for users/roles/permissions.

### Views
- Not fully inspected in this pass; gaps inferred from issue tracker + controller messages.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Soft delete user | Redirect + flash | Same | PASS |
| Restore user | Redirect + flash | Same | PASS |
| Force delete user | Redirect + flash | Same | PASS |
| Bulk soft delete | Redirect + flash count | Same | PASS |
| Bulk force delete | Redirect + flash count | Same | PASS |
| Restore icon/button label | Unknown | Should use `ui.restore` | UNCLEAR |
| Deleted message consistency | Inconsistent across resources | Consistent messaging | GAP |
| Feature group settings label | Shows `ui.feature_group_settings` | Should match sidebar/system label | GAP |

## 6. Cross-Feature Dependencies

- RBAC: restore/force-delete gated by permissions.
- i18n: UI strings should use consistent translation keys.

## 7. Security Assessment

- No direct security issues observed in UI layer beyond authorization already enforced.

## 8. Maintainability Assessment

- Repeated flash message keys across controllers; acceptable.

## 9. UX Assessment

- Inconsistent wording/labels reduce perceived polish.

## 10. Identified Gaps

### GAP-UI-001
**Title:** Inconsistent soft-delete/restore messaging and icons
**Severity:** Low
**Category:** UX / Consistency
**Evidence:** Issue tracker notes inconsistent deleted messages and restore icon wording.
**Current behavior:** Visual/text inconsistency across users/roles/permissions pages.
**Expected behavior:** Uniform restore/delete messaging and icons across modules.
**Likely root cause:** Implemented per-module without shared partials.
**Affected components:** Access views, restore buttons/messages.
**Related QA cases:** QA-045, QA-046, QA-047, QA-048.

### GAP-UI-002
**Title:** Feature page group label mismatch
**Severity:** Low
**Category:** i18n / Consistency
**Evidence:** `features` page shows `ui.feature_group_settings`.
**Current behavior:** Label may not match sidebar/system terminology.
**Expected behavior:** Consistent group naming.
**Likely root cause:** Hardcoded label drift.
**Affected components:** `FeatureController::index` view.
**Related QA cases:** None.

## 11. Recommended Direction

- Extract shared restore/delete button partials + unified i18n keys.

## 12. Deferred / Open Questions

- Should bulk actions include selection count feedback?
