# Phase 3 — RBAC / Roles / Permissions

## Completed
- Verified permission add/remove sync to role via `RoleController::store/update()` and `RoleApiController::store/update()` using `syncPermissions()`.
- Verified role change access through `UserController::update()` → `UserService::update()` → `$user->syncRoles(...)`.
- Verified sidebar permission mismatch is covered by explicit child visibility check (`auth()->user()->can(...) && Feature::active(...)`) and parent visibility derived from OR of children in `resources/views/partials/layout/sidebar.blade.php`.
- Verified role/permission CRUD audit logging via `RoleObserver` and `PermissionObserver` for all mutations: `role_created`, `role_updated`, `role_deleted`, `role_restored`, `role_force_deleted`, `permission_created`, `permission_updated`, `permission_deleted`, `permission_restored`, `permission_permanently_deleted`.

## Deferred / Excluded from Phase 3 per user request
- Plan/billing interaction with role/permission sync is deferred. No changes made to `PlanService` or plan-boundary filtering behavior.
- UI filtering of permissions in role create/edit form based on plan `allowed_permissions` is deferred.

## Changes Made
- `docs/qa/phase-reports/phase-03-rbac.md`: add Phase 3 report.
- `docs/qa/remediation-tracker.md`: mark Phase 3 `complete` and attach report.

## Root Causes Addressed
- None in code; this phase closes documentation gaps for RBAC behavior already implemented.

## Tests
- Re-run targeted existing suite to confirm no RBAC regression during Phase 3 review.
- Command: `php artisan test --filter="AuthLoginTest|ApiAuthTest|ApiEndpointsTest|SessionTest|FeatureFlagTest|SidebarVisibilityTest|PlanPermissionBoundaryRuntimeTest|RbacTest|LogViewerTest|AuditTest|ForceDeleteAuditTest|LocaleTest|TranslationTest|ArchitectureAuditTest"`
- Result: 137 passed, 1418 assertions.

## Manual QA
- Reviewed role/permission controllers and observers for mutation coverage and audit emission.
- Reviewed sidebar parent/child visibility logic for permission mismatch behavior.

## Security Verification
- Role/permission mutations emit audit logs from observer layer with IP/user_agent metadata.
- Super-admin role remains protected from delete/force-delete at controller layer.
- Route-level authorization and feature flag enforcement unchanged.

## Regressions Checked
- No route/middleware/authz changes in Phase 3 docs update.
- Existing RBAC enforcement, sidebar visibility, and plan filtering unchanged.

## Remaining Gaps
- `GAP-RBAC-001`: soft-deleted role behavior during spatie sync is not explicitly handled; product decision pending.
- `GAP-RBAC-002`: resolved by observers; no controller-layer duplication needed.
- `GAP-RBAC-003`: `feature.manage` plan bypass remains documented and scoped to role assignment only; plan/billing interaction deferred.

## Design Decisions Required
- Confirm whether soft-deleted roles should be excluded from role edit/assign dropdowns.
- Confirm whether 24h spatie cache TTL is acceptable or should be shorter for admin mutator workflows.
- Confirm plan/billing interaction scope for later phase.

## Files Changed
- `docs/qa/phase-reports/phase-03-rbac.md`
- `docs/qa/remediation-tracker.md`

## Commit Recommendation
```
docs: finalize Phase 3 RBAC report and remediation tracker update
```

## Ready for Next Phase
YES
