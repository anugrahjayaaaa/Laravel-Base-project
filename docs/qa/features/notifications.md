# Feature 11 — Notifications

## 1. Scope

Included:
- Notification creation
- Notification delivery
- Notification list
- Read/unread state
- Mark read behavior
- Authorization
- Cross-user isolation
- Persistence
- Triggering events
- Failure behavior

Explicitly out of scope:
- Email delivery infrastructure beyond verification/reset
- Push notifications

## 2. Related QA Cases

- QA-062 notifications list
- QA-063 notification read/unread

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Uses Laravel native database notifications.
- Only `AuditNotification` is implemented, dispatched by `LogAuthentication` listener on auth events.
- Notifications page marks all as read on view.
- Notifications route gated by `feature:audit` + `can:audit.view`.
- API notifications endpoints exist under `/api/v1/notifications` with list/unread-count/mark-all-read.
- Backfill command exists to convert activity log entries into notifications.

## 4. Current Implementation

### Notifications
- `AuditNotification` uses `database` channel only.
- `toMail()` exists but `via()` returns only `database`.

### Controllers
- Web: `NotificationController::index` marks all read on view.
- API: `NotificationApiController` provides list/unread-count/mark-all-read.

### Tests
- Confirms notifications page shows entries.
- Confirms view marks all as read.
- Confirms authorization denial.
- Confirms backfill command.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| Auth event triggers notification | Database notification created | Same | PASS |
| View notifications page | Lists + marks all read | Same | PASS |
| Unread count after view | 0 | Same | PASS |
| API list notifications | JSON list | Same | PASS |
| API mark all read | Marked | Same | PASS |
| User without audit.view | 403 | Same | PASS |
| Feature flag audit off | 404 | Same | PASS |
| Non-auth notifications | None implemented | Debatable | DESIGN QUESTION |
| Notification failure | No retry/fallback | Acceptable for DB channel | PASS |

## 6. Cross-Feature Dependencies

- Audit: notifications mirror audit events for user visibility.
- Auth: notification creation tied to auth events.

## 7. Security Assessment

- Notifications are per-user via Laravel notifiable; no cross-user leakage observed.
- No sensitive data beyond action label + IP.

## 8. Maintainability Assessment

- Single notification class; easy to extend.
- Backfill command provides migration path from audit to notifications.

## 9. UX Assessment

- Mark-all-read on view is simple but loses per-item read state control.
- No pagination beyond 20 per page.

## 10. Identified Gaps

### GAP-NOTIF-001
**Title:** Notifications are limited to auth events only
**Severity:** Low
**Category:** Functional / UX
**Evidence:** Only `AuditNotification` exists and is only dispatched from `LogAuthentication`.
**Current behavior:** No notifications for user management, role/permission changes, system events.
**Expected behavior:** Depends on product needs; current scope may be sufficient.
**Likely root cause:** Minimal viable implementation.
**Affected components:** `NotificationController`, notifications system.
**Related QA cases:** QA-062, QA-063.

## 11. Recommended Direction

- Define notification strategy: auth-only vs broader system events.

## 12. Deferred / Open Questions

- Should non-auth mutations generate notifications?
