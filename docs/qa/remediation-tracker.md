# Remediation Tracker

Sequential stabilization order. One phase at a time. Plan/billing deferred.

| Phase | Feature | Status | Report |
|---|---|---|---|
| 1 | Authentication Core | `complete` | `docs/qa/phase-reports/phase-01-authentication.md` |
| 2 | User Lifecycle / User CRUD | `pending` | — |
| 3 | RBAC / Roles / Permissions | `pending` | — |
| 4 | Feature Flags + Navigation | `pending` | — |
| 5 | Sessions | `pending` | — |
| 6 | Profile + Password + Verification | `pending` | — |
| 7 | Settings + Registration | `pending` | — |
| 8 | Audit | `pending` | — |
| 9 | Notifications | `pending` | — |
| 10 | Logs | `pending` | — |
| 11 | Translation / i18n | `pending` | — |
| 12 | UI Consistency / Shared Components | `pending` | — |
| 13 | Dashboard | `pending` | — |
| 14 | API Hardening | `pending` | — |
| 15 | Cross-Feature Regression | `pending` | — |

Phase 1 includes: login-time lock enforcement, API lock enforcement, login toggle i18n, locked-user modal before redirect to login.
Phase 2 includes: admin-create random password in form + email, verification email handling, username/email uniqueness behavior, delete/restore auth behavior.
Phase 3 includes: permission add/remove sync to role, role change access, sidebar permission mismatch.
Phase 4 includes: feature page label `ui.feature_group_settings` review.
Phase 5 includes: session invalidation on admin-lock for already-logged-in user, session list permission gating, header user-dropdown session link visibility, logout-others password UX alignment, same-credential login on new device/incognito must logout previous session.
Phase 8 includes: audit coverage review across all mutation features.
Phase 9 includes: notification behavior review + gap analysis.
Phase 10 includes: log search bar implementation.
Phase 11 includes: `$title` architecture + translation add button.
Phase 12 includes: restore icon `ui.restore`, soft-delete messaging consistency, shared UI components.
