# Remediation Tracker

Sequential stabilization order. One phase at a time. Plan/billing deferred.

| Phase | Feature | Status | Report |
|---|---|---|---|
| 1 | Authentication Core | `complete` | `docs/qa/phase-reports/phase-01-authentication.md` |
| 2 | User Lifecycle / User CRUD | `complete` | `docs/qa/phase-reports/phase-02-user-lifecycle.md` |
| 3 | RBAC / Roles / Permissions | `complete` | `docs/qa/phase-reports/phase-03-rbac.md` |
|| 4 | Feature Flags + Navigation | `complete` | (label alignment `feature_group_settings` unified) |
| 5 | Sessions | `complete` | `docs/qa/phase-reports/phase-05-sessions.md` |
| 6 | Profile + Password + Verification | `complete` | `docs/qa/phase-reports/phase-06-profile-password-verification.md` |
|| 7 | Settings + Registration | `complete` | feature toggle audit log added |
|| 8 | Audit | `complete` | observer cleanup (created fallback only), service audit scoped with on($license), all controllers use Auditable trait |
|| 9 | Notifications | `complete` | mark-all-read audit added (web + API) |
|| 10 | Logs | `complete` | search bar implemented + test added |
|| 11 | Translation / i18n | `complete` | \$title uses ui() key; create form + add button added; store + audit |
|| 12 | UI Consistency / Shared Components | `complete` | unified restore/delete icons via action-buttons component; i18n title keys |
| 13 | Dashboard | `complete` | recent activity feed + auditCount card |
| 14 | API Hardening | `complete` | added restore/force-delete routes + manage permissions |
| 15 | Cross-Feature Regression | `pending` | — |

Phase 1 includes: login-time lock enforcement, API lock enforcement, login toggle i18n, locked-user modal before redirect to login, auto-lock audit log.
Phase 2 includes: admin-create random password in form + email, verification email handling, username/email uniqueness behavior, delete/restore auth behavior, user CRUD audit log.
Phase 3 includes: permission add/remove sync to role, role change access, sidebar permission mismatch, role/permission CRUD audit log.
Phase 4 includes: feature page group label alignment (`feature_group_settings` in code/lang/config/docs — previously `feature_group_system`, now unified).
Phase 5 includes: session invalidation on admin-lock for already-logged-in user, session list permission gating, header user-dropdown session link visibility, logout-others password UX alignment, same-credential login on new device/incognito must logout previous session, session logout audit log.
Phase 6 includes: profile update audit log, password change audit log, verification flow coverage for web and API.
Phase 7 includes: registration settings, system settings update, feature toggle audit log.
Phase 8 includes: audit coverage review across all mutation features, audit-log DRY review (repeated `activity()->causedBy()->withProperties()->performedOn()->log()` across controllers/observers — evaluate helper/trait vs current inline pattern), move audit logs out of services/observers into controllers where HTTP request context exists (e.g. `LicenseService::activate/revoke`, `UserObserver::created`).
Phase 9 includes: notification behavior review + gap analysis, notification mark-all-read audit log.
Phase 10 includes: log search bar implementation.
Phase 11 includes: `$title` architecture + translation add button.
Phase 12 includes: restore icon `ui.restore`, soft-delete messaging consistency, shared UI components.
Phase 13 includes: dashboard audit log.
Phase 14 includes: API token create/revoke audit log.
Phase 15 includes: cross-feature regression testing for all audit logs.
