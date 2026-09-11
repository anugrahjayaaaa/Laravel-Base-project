# REST API (`/api/v1`)

Auth: Sanctum **Bearer** token — `Authorization: Bearer <token>`. Get a token from `POST /api/v1/login`.
All bodies are JSON; errors return `{ "message": "...", "errors": {...} }`.

Interactive docs (Scribe): `/docs` · OpenAPI `/docs.openapi` · Postman `/docs.postman`.

## Endpoints

| Method | Path | Auth | Notes |
|--------|------|------|-------|
| POST | `/login` | public | `identifier` (email/username), `password`, `device_name` |
| POST | `/forgot-password` | public | sends reset link |
| POST | `/reset-password` | public | `token`, `email`, `password` |
| GET | `/email/verify` | public | `?id=&hash=&expires=&signature=` |
| POST | `/email/verify/resend` | token | resend verification |
| GET | `/me` | token | current user |
| POST | `/logout` | token | revokes current token |
| POST | `/password/change` | token | requires current password |
| GET/PUT | `/profile` | token | own profile |
| POST | `/profile/password` | token | change own password |
| GET | `/sessions` | token | active sessions |
| POST | `/sessions/logout-others` | token | revoke other sessions |
| GET/POST/DELETE | `/api-tokens` `/api-tokens/{id}` | token | manage own tokens |
| GET | `/notifications` | token | list + mark read |
| GET | `/notifications/unread-count` | token | unread count |
| POST | `/notifications/mark-all-read` | token | mark all read |
| GET | `/features` | token | list flags |
| POST | `/features/{slug}/toggle` | `feature.manage` | toggle flag |
| GET | `/audit` | `audit.view` | filtered list |
| GET | `/audit/actions` | `audit.view` | distinct action types |
| GET/POST | `/users` | `user.view` | list/create |
| GET/PUT/DELETE | `/users/{id}` | `user.view`/`user.update` | show/update/soft-delete |
| POST | `/users/{id}/lock` `/unlock` `/reset-password` | `user.update` | admin actions |
| POST | `/users/{id}/restore` `/force-delete` | `user.update` | restore/permanently delete |
| GET/POST | `/roles` | `role.view` | list/create |
| GET/PUT/DELETE | `/roles/{id}` | `role.view` | show/update/delete |
| POST | `/roles/{id}/restore` `/force-delete` | `role.view` | restore/permanently delete |
| GET/POST | `/permissions` | `permission.view` | list/create |
| GET/PUT/DELETE | `/permissions/{id}` | `permission.view` | show/update/delete |
| POST | `/permissions/{id}/restore` `/force-delete` | `permission.view` | restore/permanently delete |

## Design notes

- Thin controllers: validation in existing Form Requests; responses via `JsonResource`.
- Admin routes gated with `can:*` middleware; failures → 403.
- Rate-limited auth endpoints (10/15min).
- No password / plain token in any response (token shown once on creation).
