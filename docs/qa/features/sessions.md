# Feature 09 — Sessions

## 1. Scope

Included:
- Session listing (web + API)
- Current session identification
- Other sessions identification
- Logout current
- Logout others (web + API)
- Session authorization
- Session ownership
- Password confirmation UX

Explicitly out of scope:
- Session hijacking detection beyond provided controls
- Remember-token behavior beyond logoutOtherDevices

## 2. Related QA Cases

- QA-054 sessions list
- QA-055 logout others

## 3. Current Runtime Behavior

### Observed (from code + tests)
- Web session list queries `sessions` table for `user_id = auth()->id()`.
- Session row labels current device vs other.
- Logout others:
  - Deletes other DB session rows for current user.
  - Optionally calls `Auth::logoutOtherDevices($password)` if password filled.
- API sessions endpoint lists sessions similarly; logout others behaves same.
- Route protection: `feature:sessions` + `can:session.view` for list; `can:session.revoke` for logout others.

## 4. Current Implementation

### Controllers
- `SessionController::index/logoutOthers`
- `SessionApiController::index/logoutOthers`

### Requests
- `LogoutOthersRequest`: password nullable string.

### UI
- `settings/sessions.blade.php` shows table + password field + logout button.
- Current session shown with badge; others labeled.

## 5. Behavior Matrix

| Scenario | Current Behavior | Expected Behavior | Status |
|---|---|---|---|
| List sessions | Own rows only | Same | PASS |
| Logout others without password | Deletes other rows only | Same | PASS |
| Logout others with password | Deletes rows + calls logoutOtherDevices | Same | PASS |
| API list sessions | JSON list | Same | PASS |
| API logout others without password | Deletes rows | Same | PASS |
| Staff without session.view | 403 | Same | PASS |
| Feature flag off for sessions | 404 | Same | PASS |

## 6. Cross-Feature Dependencies

- Auth: session list/logout requires authenticated user.
- Password change: also invalidates other sessions.

## 7. Security Assessment

- Password confirmation is optional for logout others; security-sensitive action without mandatory re-auth.
- Session ownership enforced by querying `user_id = auth()->id()`.

## 8. Maintainability Assessment

- Web and API controllers share identical logic with small serialization differences.

## 9. UX Assessment

- Password field is present but optional in UI; may confuse users about whether it is required.

## 10. Identified Gaps

### GAP-SESSION-001
**Title:** Logout others does not require mandatory password confirmation
**Severity:** Medium
**Category:** Security / UX
**Evidence:** `LogoutOthersRequest` allows `nullable|string`; UI shows optional password input. Tested: `POST /sessions/logout-others` with empty password succeeds.
**Current behavior:** Users can terminate all other sessions without proving identity.
**Expected behavior:** Security-sensitive action should require password confirmation.
**Likely root cause:** Convenience-first design.
**Affected components:** `SessionController`, `SessionApiController`, `LogoutOthersRequest`, `settings/sessions.blade.php`.
**Related QA cases:** QA-054, QA-055.

## 11. Recommended Direction

- Make password required for logout others and align web/API UX.

## 12. Deferred / Open Questions

- Should session list include remember-token status or device type parsing?
