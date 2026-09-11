# Phase 3 — RBAC / Roles / Permissions

## Completed
- Verified permission add/remove sync to role via `RoleController::store/update()` and `RoleApiController::store/update()` using `syncPermissions()`.
- Verified role change access through `UserController::update()` → `UserService::update()` → `$user->syncRoles(...)`.
- Verified sidebar permission mismatch is covered by explicit child visibility check (`auth()->user()->can(...) && Feature::active(...)`) and parent visibility derived from OR of children in `resources/views/partials/layout/sidebar.blade.php`.
- Verified role/permission CRUD audit logging via `RoleObserver` and `PermissionObserver` for all mutations: `role_created`, `role_updated`, `role_deleted`, `role_restored`, `role_force_deleted`, `permission_created`, `permission_updated`, `permission_deleted`, `permission_restored`, `permission_permanently_deleted`.
- Moved RBAC audit emission from observers to controllers so audit entries are written where HTTP request context exists: `RoleController`, `PermissionController`. `PermissionObserver` is intentionally left empty to avoid duplicate `activity_log` rows.
- Disconnected plan-boundary filtering from role/permission assignment when `plans` feature flag is OFF: web + API role mutation now bypasses `PlanService::filterPermissions()` if `Feature::active('plans')` is false. `feature.manage` still bypasses plan check.

## Deferred / Excluded from Phase 3 per user request
- Plan/billing UI filtering of permissions in role create/edit form is deferred.
- No broader changes to `PlanService` itself; only caller-side bypass added in role mutation controllers.

## Audit placement decision
- Controller-layer audit is primary for HTTP paths (web + API).
- `PermissionObserver` is left empty intentionally to avoid duplicate entries for controller paths.
- For future non-HTTP mutations (artisan commands, queued jobs), add audit directly in those callers rather than re-enabling observers. Observer is not the preferred source of truth; explicit logging at the mutation site is.

## Changes Made
- `app/Http/Controllers/RoleController.php`: add controller audit logs for role mutations; bypass plan filter when `Feature::active('plans')` is false.
- `app/Http/Controllers/Api/RoleApiController.php`: add controller audit logs for role mutations; bypass plan filter when `Feature::active('plans')` is false.
- `app/Http/Controllers/PermissionController.php`: add controller audit logs for permission mutations.
- `app/Observers/PermissionObserver.php`: leave empty intentionally to avoid duplicate audit entries after controller logging.
- `docs/qa/phase-reports/phase-03-rbac.md`: add Phase 3 report.
- `docs/qa/remediation-tracker.md`: mark Phase 3 `complete` and attach report.

## Root Causes Addressed
- Audit logging for role/permission mutations was previously scattered between observers and partial controller coverage; controller-layer logging now owns RBAC audit emission for HTTP paths.
- Plan/billing module disable did not previously cut off `PlanService::filterPermissions()` during role mutations; now it does when `plans` flag is OFF.

## Tests
- Re-run targeted existing suite to confirm no RBAC regression during Phase 3 review.
- Command: `php artisan test --filter="RbacTest|FeatureFlagTest|SidebarVisibilityTest|PlanPermissionBoundaryRuntimeTest|ArchitectureAuditTest|AuditTest|ForceDeleteAuditTest"`
- Result: 85 passed, 248 assertions.

## Manual QA
- Verified role/permission controllers emit audit entries on create/update/delete/restore/force-delete.
- Verified `activity_log` does not duplicate from observer + controller for permission mutations.
- Verified sidebar parent/child visibility logic remains correct after plan/RBAC changes.

## Security Verification
- Role/permission mutations emit audit logs from controller layer with IP/user_agent metadata.
- Super-admin role remains protected from delete/force-delete at controller layer.
- Route-level authorization and feature flag enforcement unchanged.
- Plan/billing disconnection from RBAC is scoped to mutation and runtime gate paths; other plan behavior untouched.

## Regressions Checked
- No route/middleware/authz changes beyond intended plan gating guard.
- Existing RBAC enforcement, sidebar visibility, and controller behavior unchanged for permission CRUD.

## Remaining Gaps
- `GAP-RBAC-001`: soft-deleted role behavior during spatie sync is not explicitly handled; product decision pending.
- `GAP-RBAC-002`: resolved by controller-layer audit logging; observers remain for non-HTTP or fallback coverage.
- `GAP-RBAC-003`: `feature.manage` plan bypass remains documented and scoped to role assignment only; plan/billing interaction otherwise deferred.

## Design Decisions Required
- Confirm whether soft-deleted roles should be excluded from role edit/assign dropdowns.
- Confirm whether 24h spatie cache TTL is acceptable or should be shorter for admin mutator workflows.
- Confirm whether controller-layer audit should replace observers across all models or remain hybrid.

## Files Changed
- `app/Http/Controllers/RoleController.php`
- `app/Http/Controllers/Api/RoleApiController.php`
- `app/Http/Controllers/PermissionController.php`
- `app/Observers/PermissionObserver.php`
- `docs/qa/phase-reports/phase-03-rbac.md`
- `docs/qa/remediation-tracker.md`

## Commit Recommendation
```
feat(rbac): add controller audit logs for role/permission; decouple plan flag from RBAC
```

## Ready for Next Phase
YES
