# Architecture

System-level knowledge for the Laravel Base Project.

- [Architecture overview](./overview.md) — stack, layered architecture, v1 modules, locked decisions.

## Architecture Decision Records (ADRs)

- [`README.md`](./decisions/README.md) — ADR index + full records (single file;
  each decision is cross-referenceable by its `ADR-NNNN` id).

| ID | Title |
|----|-------|
| ADR-0001 | AdminLTE 4.9.1 (zip) |
| ADR-0002 | RBAC via spatie/laravel-permission |
| ADR-0003 | Mobile auth = Sanctum token |
| ADR-0004 | Soft delete + automatic audit |
| ADR-0005 | Dark mode default |
| ADR-0006 | Single-tenant v1 |
| ADR-0007 | Email verification only, no MFA in v1 |
| ADR-0008 | Sidebar "Template" section |
| ADR-0009 | Feature flags above RBAC |
| ADR-0010 | Authorization gate on the route, not the controller |
