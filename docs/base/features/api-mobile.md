---
id: BASE-010
name: Mobile API
status: implemented
---

# API for Mobile

One app: web (session) + mobile (Sanctum token).

## Conventions
- Prefix `/api/v1`, versioned from day one.
- JSON, standard status codes (2xx/4xx/5xx), structured errors:
  `{"error": {"code": "VALIDATION", "message": "...", "details": {...}}}`.
- All lists: pagination + filter + sort (keyset for large data).
- Rate limit globally + strict on auth endpoints.

## Mobile auth
- Login → issue Sanctum token (ability: `mobile`).
- Request sends `Authorization: Bearer`.
- Logout → `token->delete()`. Change password → revoke all tokens.
- Mobile failed login follows lockout + audit (same as web).
- Mobile login also rejects permanently/temporarily locked accounts before password check.

## Core v1 endpoints
- `POST /api/v1/login` (username|email + password)
- `POST /api/v1/logout`
- `GET  /api/v1/me` (profile + theme preference)
- `POST /api/v1/password/change`
- Business modules: added as modules are built, following `api-design-principles`.

## Docs
- OpenAPI (scribe / l5-swagger) from routes/phpdoc.
- CI fails if `openapi.yaml` is stale vs routes.

## Gate
- All endpoints protected (except login/health); authz via permission.
- Output escaped; input validated at boundary.
