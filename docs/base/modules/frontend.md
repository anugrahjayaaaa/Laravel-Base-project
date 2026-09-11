# Frontend & Theme

## Template (locked: AdminLTE 4.9.1)
- Source: GitHub release **v4.9.1** → `adminlte-4.9.1.zip` (dist only).
  https://github.com/ColorlibHQ/AdminLTE/releases
- Bootstrap 5.3.8, MIT, no jQuery.
- Integration: copy `dist/css/adminlte.min.css`, `dist/js/adminlte.min.js`,
  `plugins/` → `public/vendor/adminlte/`. Load locally (not CDN in prod).

## Sidebar structure (locked)
1. Main menu: Dashboard, Users, Roles, Permissions, Audit Log, Profile, Sessions, API Tokens.
2. **Settings** submenu: Features (feature-flag management, `feature.manage` only).
3. "Template" section (below): component reference library from the zip demo
   (`dist/pages/`): Buttons, Cards, Tables, Forms, Modals, Tabs, Badges, Alerts,
   Charts, Icons, Widgets. Read-only, for the team to copy-paste.

## Theme rules (required)
- Dark mode DEFAULT (`data-bs-theme="dark"`).
- Light/dark toggle; choice in localStorage + DB per user.
- Responsive: mobile sidebar collapses to offcanvas + backdrop.
- A11y: contrast, focus ring, form labels, aria on nav.

## Build
- Blade + committed vendor assets (`public/vendor/*`). **No npm/Vite build step** — AdminLTE/Bootstrap ship prebuilt.
- Use Bootstrap utilities + AdminLTE Sass variables; no large custom CSS.
- Icons: Bootstrap Icons. Consistency via `x-*` components.
