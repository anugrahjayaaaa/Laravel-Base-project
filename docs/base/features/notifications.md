---
id: BASE-008
name: Notifications
status: implemented
---

# Notifications

Native Laravel notifications (`laravel/notifications`, database channel) — the standard Laravel way,
not a custom pivot.

## How it works
- Auth events (login, logout, failed login, password reset, email verified) are dispatched from
  `App\Listeners\LogAuthentication` via `AuthEvent` → `AuditNotification`.
- Stored in the `notifications` table (created by `php artisan notifications:table`).
- User model already uses the `Notifiable` trait, so `$user->notify(...)` works out of the box.

## UI
- **Header bell** — unread count badge + dropdown of recent items (human-readable labels).
- **Notifications page** (`/notifications`, gated by `audit.view` + `feature:audit`) — lists all,
  marks read on view, paginated.

## Backfill
`php artisan notifications:backfill` — copies existing auth activity from `activity_log` into
`notifications` (idempotent, keyed by user + type + created_at). Run once after deploy to populate
history.

## Labels
`AuditNotification::label()` maps raw action keys (`login_failed`, `login_success`, …) to readable
text. Both the bell and the page render `data.label`.
