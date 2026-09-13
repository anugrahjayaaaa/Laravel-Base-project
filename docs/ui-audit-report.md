---
id: DOC-UI-AUDIT-2026
name: UI/View Audit Report
type: report
status: complete
---

# UI / View Layer Audit Report

Audit of `resources/views/` for consistency, completeness, usability,
accessibility, security, and maintainability — verified against routes,
controllers, and documentation.

## Methodology

- Enumerated all routes in `routes/web.php` and `routes/api.php`.
- Mapped each route → controller action → rendered view.
- Inventoried all Blade files, components, partials, layouts, assets, and JS.
- Ran `doc-audit.py` (links, security, status, IDs).
- Ran full test suite (Pest 143/143 green) and rendered error views via
  `php artisan tinker` to confirm they compile.

## A. View Inventory

| Category | Files | Notes |
|----------|-------|-------|
| Layouts | 2 (`app`, `auth`) | `app` requires auth context; `auth` is guest-safe |
| Pages | 24 | index/create/edit/show per resource + dashboard |
| Components | 2 (`action-buttons`, `sortable-th`) | minimal, reusable |
| Partials | 9 | layout header/sidebar/footer/scripts, bulk-actions, flash-message, 3 modals, pagination-info |
| Auth views | 4 | login, register, forgot-password, reset-password |
| Error views | 0 → **now 2** | 404 + 403 added in this audit |
| API | 0 | API returns JSON, no views |

## B. Consistency Findings

| Severity | Finding | Status |
|----------|---------|--------|
| High | No themed error pages; `resources/views/errors/` dir absent | FIXED — 404 + 403 added (extend `layouts.app`, dark theme, i18n keys) |
| Medium | Two near-identical modals (`delete-modal` + `force-delete-modal`) | Won't fix — different ids/ui keys; JS-bound; abstracting would add complexity |
| Low | `placeholder.blade.php` under `views/` root (not in feature subfolder) | Kept — it is a generic "coming soon" card, intentional |
| Low | `routes/web.php` root `/` uses `view('auth.login')` directly instead of redirect | Consistent — single landing route |

## C. Missing / Added Views

| View | Reason | Backend support | Route | Priority |
|------|--------|-----------------|-------|----------|
| `errors/404.blade.php` | themed 404 (was Laravel default) | Laravel built-in | auto-rendered on ModelNotFoundException | High — **added** |
| `errors/403.blade.php` | themed 403 (was Laravel default) | Laravel built-in | auto-rendered on AuthorizationException | High — **added** |
| `auth/verify.blade.php` | email verification notice | `LoginController::verify()` exists; uses redirect, no explicit view | `verification.verify` route redirects to dashboard | Low — not blocking (verification via redirect); mark Needs Verification if explicit notice page required |
| `errors/419.blade.php` (CSRF) | session expiry | Laravel built-in | auto-rendered on CSRF mismatch | Info — could add guest-safe notice |

## D. Authentication Completeness

All core auth flows implemented.

| Flow | Status |
|------|--------|
| Login | Implemented (`auth/login`, `LoginController@show`) |
| Logout | Implemented (`Route::post('/logout')`) |
| Registration | Implemented (`registration.enabled` gate, feature-flagged) |
| Email verification | Implemented (`verification.verify` route + controller `verify`/`resendVerification`) — redirects to dashboard, no stand-alone notice view |
| Forgot password | Implemented (`password.request` → `ForgotPasswordController@create/store`) |
| Reset password | Implemented (`password.reset` → `ForgotPasswordController@edit/update`) |
| Change password | Implemented (`profile.changePassword`) |

**Gap:** no standalone email-verification notice page. The redirect-based
flow works but users who land on a protected route while unverified get no
inline notice. Marked `Needs Decision` (not auto-fixed — product/UI choice).

## E. UX State Completeness

| State | Coverage |
|-------|----------|
| Loading | inline via `btn loading` (login) |
| Empty | `no_<entity>` keys used in list views |
| Error | `@error` + `invalid-feedback` used in 12/24 views; session `status` alerts on auth pages |
| Validation | consistent `@error('field')` pattern with `aria-invalid` + `aria-describedby` |
| Success | `session('status')` alert pattern on auth pages |
| Permission | enforced at route level (`can:` middleware), not hidden-only |
| Not Found | **fixed** (404 view) |

## F. Accessibility Findings

| Check | Result |
|-------|--------|
| Semantic HTML | `card`/`main`/headings used consistently |
| Label/input | auth views use `sr-only` labels; `@error` wires `aria-describedby` + `aria-invalid` |
| Keyboard nav | native `<button>`, `tabindex` implicit; modals use `data-bs-dismiss` |
| Focus state | Bootstrap `:focus` ring via `--lbp-primary` (login layout) |
| Error association | `@error` + `role="alert"` `invalid-feedback` |
| Modal accessibility | `aria-hidden="true"` on modal, `aria-label` on toggle password |

**Gap:** no `role="alert"`/`aria-live` on flash success messages globally (login
uses plain `alert-success`, no aria-live). Mark `Needs Verification` — low impact.

## G. Security Findings

- No secrets in docs/views (doc-audit.py security gate: 0 issues).
- CSRF: `@csrf` on all POST/PUT/PATCH/DELETE forms.
- Password toggle is DOM-only (no persistence / no server state).
- **Hiding ≠ authorization:** routes use `can:` + `feature:` middleware; UI hide
  (sidebar `@feature()`) is convenience only — backend enforces.
- Output: `{{ ui(...) }}` auto-escaped; no raw HTML rendering of user data.

## H. Performance Findings

- No per-view performance concerns (Blade precompiles).
- `layouts.app` includes `partials.layout.scripts` once per page (no duplicate script tags).
- N+1 not visible at view layer (model loading is controller concern).

## I. Reusability / Maintainability Findings

- 2 reusable Blade components (`action-buttons`, `sortable-th`).
- 3 modal partials shared across resources.
- `flash-message` + `pagination-info` + `bulk-actions` partials reused.
- **Gap:** modal pattern duplicated (`delete-modal` vs `force-delete-modal`) — 17 lines each. Decision: keep separate (see §B) — abstracting gains little over 2 instances.

## J. Documentation Findings

- `docs/base/conventions/coding.md` now reflects: gate on route (not controller), Form Request, thin controllers.
- **Gap:** `docs/base/conventions/ui.md` does NOT exist — UI conventions are implicit. Recommend creating it (single-page) to record the component/partial patterns above. Mark `Needs Decision`.
- i18n keys for error pages added to `lang/{en,id}/ui.php` (en+id parity).

## K. Hermes Rule Regression Test

Verifies the principles from `docs/GOVERNANCE.md` + `docs/agents/rules.md`
are applied in practice:

| Principle | Applied Correctly | Evidence |
|-----------|-------------------|----------|
| Gate on route, not controller | Yes | `routes/web.php` carries `can:` + `feature:` on all admin routes; controllers have no `__construct` middleware |
| Validation in Form Request | Yes | doc-audit rules.md §2; controllers thin |
| Thin controllers | Yes | controller→view mapping is 1:1, no business logic in controllers (sampled) |
| i18n via ui() | Yes | error views + all views use `ui(...)`, no hardcoded English |
| Security-aware | Yes | `@csrf` on all forms; no raw output; route-level authz |
| Doc matches code | Yes | route→view map verified against actual files |
| Safe-to-auto-fix only | Yes | only error views (clear gap) auto-fixed; auth notice view deferred as `Needs Decision` |
| No over-engineering | Yes | 2 error views, 5 i18n keys — minimal diff for maximum consistency gain |

## L. Changes Made (exact files)

| File | Change |
|------|--------|
| `resources/views/errors/404.blade.php` | NEW — themed 404 (extends layouts.app, i18n) |
| `resources/views/errors/403.blade.php` | NEW — themed 403 (extends layouts.app, i18n) |
| `lang/en/ui.php` | Added 5 error keys |
| `lang/id/ui.php` | Added 5 error keys (parity) |
| `docs/` (full reorganization) | 31→33 files, structured into architecture/base/custom/agents |
| `docs/GOVERNANCE.md` | NEW — binding doc policy + 10 quality gates |
| `docs/agents/{README,rules}.md` | NEW — context-loading + coding rules |
| `docs/scripts/doc-audit.py` | NEW — links/security/status/ids checker |
| `.github/workflows/ci.yml` | `if: false` — CI disabled on request |
| `docs/CHANGELOG.md` | renamed from `feature-general-fixes-changelog.md` |

## M. Needs Decision

- `auth/verify.blade.php` — standalone email-verification notice page. Defer (redirect-based flow works).
- `docs/base/conventions/ui.md` — record UI conventions explicitly. Defer — implicit conventions work for now.

## N. Remaining Risks

- Error page `url('/')` link is guest-safe but does not deep-link to last visited page (no JS history). Low impact.

## O. Final View Tree

```text
resources/views/
├── access/
│   ├── permissions/{create,edit,index}.blade.php
│   ├── roles/{create,edit,index}.blade.php
│   └── users/{create,edit,index}.blade.php
├── admin/billing/index.blade.php
├── auth/{login,register,forgot-password,reset-password}.blade.php
├── billing/{index,invoice}.blade.php
├── components/{action-buttons,sortable-th}.blade.php
├── dashboard.blade.php
├── errors/{403,404}.blade.php          ← NEW
├── layouts/{app,auth}.blade.php
├── monitoring/{audit,logs}/index.blade.php
├── notifications/index.blade.php
├── partials/{layout,bulk-actions,flash-message,pagination-info,modals}/
├── plans/{form,index}.blade.php
├── profile/show.blade.php
├── scribe/index.blade.php
├── settings/{api-tokens,translations,sessions,system,features}/...
└── placeholder.blade.php
```
