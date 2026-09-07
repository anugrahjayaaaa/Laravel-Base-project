# Feature 03 — Roles & Permissions / RBAC

## 1. Scope

Included:
- Role CRUD
- Permission CRUD
- Role-permission assignment
- User-role assignment
- User direct permissions (not explicitly implemented here)
- Permission removal/restore/force-delete
- Plan-boundary permission filtering
- Authorization checks (web + API)
- Sidebar authorization
- Direct URL authorization
- Permission caching behavior
- Super-admin protection

Explicitly out of scope:
- Plan/Billing entitlement details
- License isolation
- Notification behavior
- Dashboard data visibility beyond route safety

## 2. Related QA Cases

- QA-028 create role
- QA-029 duplicate role
- QA-030 assign permissions
- QA-031 delete in-use role
- QA-032 restore role
- QA-033 force delete role
- QA-034 bulk delete roles
- QA-035 direct URL no perm
- QA-036 direct URL flag off
- QA-037 create permission
- QA-038 duplicate permission
- QA-039 delete assigned permission
- QA-040 restore/force delete permission
- QA-044 edit user role/permissions
- QA-052 users page unauthorized
- QA-053 users page feature off
- QA-081 API users no perm
- QA-082 API users flag off
- QA-083 API roles/permissions gating
- QA-098 RBAC end-to-end

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Permissions are seeded in `PermissionSeeder` as a fixed list.
- Roles seeded in `RoleSeeder`: `super-admin` and `admin` get all permissions; `staff` gets none.
- Role create/edit applies `PlanService::filterPermissions()` for non-superadmin users so plan boundaries restrict assigned permissions.
- User edit syncs roles via `UserService::update()` -> `$user->syncRoles(...)`.
- Sidebar visibility is computed in `partials/layout/sidebar.blade.php` by checking `auth()->user()->can(...)` and `Feature::active(...)` per menu item; parent visibility is OR of children.
- Route authorization uses route middleware `can:...` + `feature:...` — fail-closed.
- Spatie permission cache is enabled (24h default); cleared automatically by spatie on role/permission mutations.
- Bulk delete protects `super-admin` role via skip guard.
- No direct user-level permissions are assigned; all authorization is role-based plus plan boundary.

### Assumptions not yet confirmed
- Whether `PlanService::filterPermissions()` is active in role edit/create for all admin levels or only non-superadmin; code checks `feature.manage`, not super-admin role explicitly.
- Whether permission changes reflect immediately in open sessions/browser; spatie cache TTL is 24h unless cleared.

## 4. Current Implementation

### Controllers
- `RoleController::store/update` filters permission IDs through `PlanService::filterPermissions()`.
- `RoleController::bulk` delegates to `BulkDeleteService` with skip guard for `super-admin`.
- `PermissionController` is thin CRUD without plan filtering.
- `UserController::update` syncs roles, does not assign direct permissions.

### Services
- `PlanService::filterPermissions()` intersects submitted IDs with allowed permission IDs from plan snapshot; bypass when user has `feature.manage`.

### RBAC cache/config
- `config/permission.php` cache expiration 24h, key `spatie.permission.cache`, store default.
- Spatie events auto-clear cache on mutation.

### Sidebar
- Computed parent visibility based on child `can` + `feature` checks.
- Individual items wrapped with `@can` and `@feature`.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Create role with name | Success | Same | PASS |
| Duplicate role name | Validation error | Same | PASS |
| Assign all permissions to role | All synced | Same | PASS |
| Assign permission outside plan boundary | Filtered out for non-superadmin | Same | PASS |
| Superadmin assigns any permission | All pass through (feature.manage bypass) | Same | PASS |
| Edit user role | Roles synced | Same | PASS |
| Edit user roles clears stale permissions | Yes via `syncRoles` | Same | PASS |
| Delete role in use | Soft-deletes role, user retains historical role relation until re-sync | Need policy clarification | DESIGN QUESTION |
| Restore role | Restores row | Same | PASS |
| Force delete role | Permanently removes | Super-admin protected | PASS |
| Bulk delete roles | Counts deletions; skips protected | Same | PASS |
| Direct URL without permission | 403 | Same | PASS |
| Direct URL with flag off | 404 | Same | PASS |
| Sidebar hides when no child visible | Parent hidden by OR logic | Same | PASS |
| Permission cache after change | Auto-cleared by spatie on mutation | Same | PASS |
| API users list without permission | 403 from route middleware | Same | PASS |
| API roles/permissions gating | 403/404 per middleware | Same | PASS |
| RBAC end-to-end | Route, sidebar, API gated | Same | PASS |
| Staff with no role permissions | Cannot access `user.*` pages | Depends on plan boundary | PASS |
| Plan removes permission from allowed set | Already assigned role permission remains in DB but ineffective at runtime | Expected by design | PASS |

## 6. Cross-Feature Dependencies

- PlanService: role-permission assignment is filtered by plan boundaries.
- Feature flags: sidebar and routes require feature flag active.
- User management: role changes on user affect authorization everywhere.
- Audit: not fully covered for role/permission mutations.

## 7. Security Assessment

Strengths:
- Server-side route gates on every protected route.
- Feature flag middleware in addition to permission gate.
- Super-admin protected from delete/force-delete.
- Plan boundary enforced at role assignment and runtime via `Gate::before`.
- Spatie cache auto-clears on mutation.

Concerns:
- `feature.manage` bypasses plan filtering; if that permission is granted broadly, plan boundaries can be circumvented.
- 24h spatie cache window means stale permissions possible if cache store is array/driver in some environments.
- No explicit audit entries for role/permission create/update/delete/restore.

## 8. Maintainability Assessment

- `RoleController::filterPermissions()` centralizes plan filtering; good.
- Bulk delete reuse via `BulkDeleteService`; good.
- Sidebar visibility logic is in Blade PHP blocks; acceptable but not easily unit-tested.
- Permission list is hardcoded in seeder; any new feature permission requires manual seeder update.

## 9. UX Assessment

- Sidebar parent auto-collapses when no child visible; good.
- Role/permission pages standard CRUD UX; no major inconsistencies observed.

## 10. Identified Gaps

### GAP-RBAC-001
**Title:** Role deletion while still assigned to users can leave dangling historical access assumptions
**Severity:** Medium
**Category:** Data integrity / UX
**Evidence:** `RoleController::destroy()` soft-deletes role; spatie `syncPermissions` on user update may reattach? Need confirm soft-deleted role behavior in spatie sync.
**Current behavior:** Role soft-deleted; users may still show historical role assignment depending on query.
**Expected behavior:** Clear product rule: prevent deletion of in-use role OR reassign users to default role.
**Impact:** Confusing admin UX; potential orphaned role references.
**Likely root cause:** No in-use guard before delete.
**Affected components:** `RoleController::destroy`, `UserService::update`, spatie role sync.
**Related QA cases:** QA-031, QA-044.

### GAP-RBAC-002
**Title:** Missing audit for role/permission mutations
**Severity:** Medium
**Category:** Audit / Maintainability
**Evidence:** `RoleController` and `PermissionController` do not log activity for create/update/delete/restore/force-delete.
**Current behavior:** No audit record for role/permission changes.
**Expected behavior:** Each mutation should emit `role_created`, `role_updated`, `role_deleted`, etc.
**Impact:** Incomplete audit trail.
**Likely root cause:** Partial adoption; only user lock/unlock/reset-link logged.
**Affected components:** `RoleController`, `PermissionController`.
**Related QA cases:** QA-060, QA-061.

### GAP-RBAC-003
**Title:** Plan boundary bypass via `feature.manage`
**Severity:** Low
**Category:** Security / Authorization
**Evidence:** `RoleController::filterPermissions()` bypasses plan filtering when `$request->user()->can('feature.manage')`.
**Current behavior:** Any user with `feature.manage` can assign permissions outside plan boundary.
**Expected behavior:** If plan boundaries are meant as hard commercial limits, even managers should respect them or have explicit override rationale.
**Impact:** Potential entitlement escape.
**Likely root cause:** Intentional override for admins, but not explicitly documented.
**Affected components:** `RoleController::filterPermissions()`, `PlanService`.
**Related QA cases:** QA-083.

## 11. Recommended Direction

- Decide policy for deleting roles still assigned to users; if deletion is allowed, show a warning or reassign users.
- Add activity logging for role/permission mutations.
- Document `feature.manage` as plan-boundary override and confirm it is intentional.

## 12. Deferred / Open Questions

- Should plan permission filtering also apply to direct user permissions if supported later?
- Should permission cache TTL remain 24h in production, or be shorter for admin-heavy mutators?
