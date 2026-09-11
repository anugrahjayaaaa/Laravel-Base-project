---
id: BASE-UI-001
name: UI Conventions
type: base
status: documented
---

# UI Conventions

Conventions derived from the existing `resources/views/` implementation.
Do NOT introduce new visual patterns — follow these exactly so views stay
consistent with the AdminLTE 4 / Bootstrap 5.3 dark theme.

Source of truth: actual view files. If a convention is not observed in the
code, it does not exist — mark it `Needs Verification`.

## 1. Layout structure

### Two layouts (no others)

| Layout | File | Use |
|--------|------|-----|
| `layouts.app` | `resources/views/layouts/app.blade.php` | all authenticated, themable (sidebar + header + footer) |
| `layouts.auth` | `resources/views/layouts/auth.blade.php` | guest auth flows (login/register/forgot/reset) |

Every page view MUST `@extends` one of these.

### Page skeleton (layouts.app)

1. header `@include('partials.layout.header')`
2. sidebar `@include('partials.layout.sidebar')`
3. main `app-main` → `app-content-header` (h3 title) + `app-content` (`@yield('content')`)
4. footer `@include('partials.layout.footer')`
5. scripts `@include('partials.layout.scripts')` + `@stack('scripts')`

### Page skeleton (layouts.auth)

- `body.login-page` (centered card, `max-width:400px`)
- `@yield('content')`
- Bootstrap JS + `@stack('scripts')`

## 2. Page header

- List pages: `<h3>{{ ui('<entity>') }}</h3>` top-left.
- Create/Edit forms: `<h3>{{ isset($model) ? ui('edit').' '.ui('<entity>') : ui('new_<entity>') }}</h3>`.
- Always precede with `@include('partials.flash-message')`.

## 3. Cards and spacing

- Primary container: `card shadow-sm`.
- Tables: `card-body p-0` → `div.table-responsive` → `table.table.table-hover.align-middle.m-0`.
- Create/Edit forms: `card` → `card-body` → `.mb-3` field groups → `card-footer.d-flex.justify-content-end.gap-2`.
- Spacing classes: Bootstrap spacing (`mb-3`, `gap-2`, `py-4`, `px-3`). No custom margin utilities.

## 4. Colors (theme tokens)

All color is via CSS variables in `public/vendor/app-theme.css` (`--lbp-*`). Blade does NOT use raw hex. Tokens (from `app-theme.css`):

| Token | Use |
|--------|-----|
| `--lbp-bg` | page background |
| `--lbp-surface` / `--lbp-surface-2` | cards/inputs |
| `--lbp-text` / `--lbp-muted` | text |
| `--lbp-primary` | primary buttons, focus ring |
| `--lbp-border` | borders, input |
| `--lbp-radius` | border-radius |
| `--lbp-shadow` | card shadow |

Default theme: `data-bs-theme="dark"` (set on `<html>`). No per-view theme switches.

## 5. Buttons

Standard Bootstrap button classes only:

| Intent | Class |
|--------|-------|
| Primary action | `btn btn-primary` |
| Secondary/cancel | `btn btn-link` |
| Destructive | `btn btn-danger` |
| Soft-destructive | `btn btn-sm btn-light border rounded-2` |
| Disabled | `disabled` attribute (or `:disabled` binding) |

Button placement:
- Form footer: `card-footer d-flex justify-content-end gap-2` (cancel link `btn btn-link`, save `btn btn-primary`).
- Form row action buttons: `btn btn-sm btn-light border rounded-2` with `data-bs-toggle="tooltip"` + `aria-label` + `style="min-width:38px"`.

## 6. Forms and inputs

- `<form method="POST" action="...">` + `@csrf`.
- Updates: `@method('PUT')` after `@csrf`.
- Field group: `<div class="mb-3">`.
- Label: `<label class="form-label">`.
- Input: `form-control @error('x') is-invalid @enderror`, with `id`, `required`, `aria-describedby="x-error"`, and `@error('x') aria-invalid="true" @enderror`.
- Error block: `@error('x')<div id="x-error" class="invalid-feedback d-block w-100" role="alert" aria-live="polite">{{ $message }}</div>@enderror`.
- Validation messages come from Form Request `messages()` → shown via `{{ $message }}` (translated keys resolved by Laravel + spatie language_lines).
- Preserve old input: `value="{{ old('x', $model->x ?? '') }}"`.
- Checkboxes: `form-check-input` + `form-check-label for="id"`.
- `<x-sortable-th>` component for sortable table headers.

## 7. Validation / error states

- Server errors: `@error('field')` + `invalid-feedback d-block w-100`.
- General form error block: `partials.flash-message` renders `session('status')` (success) and `session('error')` (danger).
- Form request must drive validation; controllers NEVER call `$request->validate()` inline.

## 8. Alerts

- Success: `alert alert-success` (via `session('status')`).
- Error: `alert alert-danger` (via `session('error')` or `@error`).
- Login auth page uses `<div class="alert alert-danger py-2">{{ $errors->first() }}</div>`.

## 9. Badges

Status badges (users list):

| Meaning | Class |
|---------|-------|
| active | `badge text-bg-success` |
| locked | `badge text-bg-warning` |
| deleted (soft) | `badge text-bg-danger` |
| perm locked | `badge text-bg-danger` |
| role tag | `badge text-bg-info me-1` |

## 10. Tables

- Wrap in `div.table-responsive`.
- Head: `th` text labels via `ui()`. Sortable columns use `<x-sortable-th>`.
- Row selection: `input.form-check-input` with `form="bulk-form"` + `name="ids[]"`.
- Empty state: `@forelse ... @empty` → `<tr><td colspan="N" class="text-center text-muted py-4">{{ ui('no_<entity>_found') }}</td></tr>`.
- Soft-deleted rows: `tr.row-deleted` (subtle red tint, applied via inline `<style>` in `partials.bulk-actions`).
- Pagination: `{{ $items->links() }}` (Laravel default), preceded by `@include('partials.pagination-info', ['items' => $items])`.

## 11. Cards

- Main content card: `card shadow-sm`.
- Dashboard/info cards: `card` with `card-header` + `card-body`.

## 12. Modals

Two patterns in `partials/modals/`:

| Modal | Use |
|-------|-----|
| `delete-modal` | soft-delete confirmation |
| `force-delete-modal` | permanent delete confirmation |

Modal scaffold (shared style):

```blade
<div class="modal fade" id="..." tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header"><h5 class="modal-title">{{ ui(...) }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">{{ ui(...) }}</div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{ ui('cancel') }}</button>
        <form ...>@csrf @method('DELETE')
          <button type="submit" class="btn btn-danger">{{ ui(...) }}</button>
        </form>
      </div>
    </div>
  </div>
</div>
```

- `id` + form `id` must be unique per modal.
- `btn-close` has no `aria-label` (Bootstrap adds one by JS); acceptable per framework default.

Bulk actions use a third modal (`bulkConfirmModal`) in `partials/bulk-actions`, same scaffold.

## 13. Navigation

### Sidebar (`partials/layout/sidebar.blade.php`)

- Grouped: Access Management, Monitoring, Settings, Billing, Workspace, Other.
- Each link: `nav-link {{ request()->routeIs('<route-prefix>.*') ? 'active' : '' }}` with `<i class="nav-icon bi bi-<icon>">`.
- Dropdown parents use `aria-expanded` + chevron icon.
- External tools (Telescope, Periscope) open `target="_blank" rel="noopener noreferrer"`.

### Header (`partials/layout/header.blade.php`)

- Wrapped in `@auth` (guest-invisible).
- User menu: native `<details class="user-menu">` (no Popper dep) with avatar + name + email.
- Theme toggle + locale switcher present.

## 14. Components (reusable Blade)

| Component | Props | Used by |
|-----------|-------|---------|
| `<x-sortable-th>` | `label`, `column`, `sort`, `dir` | all resource tables |
| `<x-action-buttons>` | `edit?`, `delete?`, `restore?`, `forceDelete?` | user/role/permission rows |

Rule: only introduce a new component when the SAME markup repeats in 3+ distinct views with identical behavior.

## 15. Scripts

- All JS lives in `partials/layout/scripts.blade.php` (Bootstrap bundle once).
- Inline page JS: `@push('scripts') ... @endpush` at end of view.
- Inline JS patterns: IIFE wrapped, `DOMContentLoaded` event delegation, no global vars.

## 16. Internationalization

- Every user-facing string → `ui('key')` (calls `ui.php` group) OR `__('messages.key')` (calls `messages.php`).
- NEVER mix: a single call site uses one helper only.
- NEVER hardcode English in Blade (`$x ?? 'fallback'`).
- New string → add to `lang/{en,id}/ui.php` + run `LanguageLineSeeder` for DB override.

## 17. Icons

Bootstrap Icons (`bi bi-<name>`) only. Classes: `bi`, `bi-<icon>`, sizing via Bootstrap (`h5`, `display-1`).

## 18. Responsive

- Uses Bootstrap 5.3 grid (`container-fluid`, `row`, `col-*`, `col-md-*`).
- Tables: `table-responsive` (horizontal scroll on overflow).
- Form action buttons stack via `gap-2` + `flex-wrap`.
- No custom breakpoints — framework defaults only.

## 19. Accessibility

Observed conventions:

- `aria-describedby` + `aria-invalid` on validated inputs.
- `role="alert"` + `aria-live="polite"` on dynamic error feedback.
- `sr-only` labels on auth inputs (e.g. login `identifier`, `password`).
- `data-bs-toggle="tooltip"` + `aria-label` on icon-only action buttons.
- Native `<button>`/`<label for>`/`<input>` semantics; no manual ARIA where HTML suffices.

Unknown / Needs Verification:
- Skip-links (not observed in layout).
- Focus-visible visible ring on all interactive elements (Bootstrap provides, but not audited per view).

## 20. When NOT to create a component

- When markup repeats 1-2 times.
- When behavior differs per instance.
- When a JS-coupled pattern has different init needs.
- When the abstraction would require passing more props than the markup itself.
