---
id: BASE-009
name: API Tokens
status: implemented
---

# API Tokens (Personal Access Tokens)

Web UI for creating and revoking Sanctum personal access tokens, plus the mobile API they authenticate
against.

## Web UI (`/api-tokens`, gated by `api-tokens.view` + `feature:api-tokens`)
- Create a token with a name; the **plain token is shown once** on creation with a copy button.
- Revoke any active token from the list.
- Token rows show the `created_at` timestamp (Sanctum tokens are non-expiring by design).

## Mobile API (Sanctum `/api/v1`)
See `api-mobile.md`. Mobile clients login → receive a Sanctum token (ability `mobile`) → send
`Authorization: Bearer ***`. Logout revokes the token; change-password revokes all tokens.

## Notes
- Passwords/tokens are never written to the audit log.
- This is the built-in Sanctum flow — no custom token package.
