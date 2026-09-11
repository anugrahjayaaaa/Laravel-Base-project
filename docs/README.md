# Laravel Base Project — Documentation

Base core system built on Laravel 13 — a reusable web admin + mobile API
foundation (auth, RBAC, audit, feature-flagged modules, licensing/billing).

This directory is the **single source of truth**. AI agents and developers
MUST read `docs/README.md` and the relevant `docs/base/...` doc before coding.
If code conflicts with docs → **docs win** (change via an ADR in
`docs/architecture/decisions/`).

## How AI agents consume these docs

Read `docs/agents/` for the context-loading rules and agent rules.
Brief:

- Existing feature task → read `docs/README.md`, the relevant
  `docs/base/features/<feature>/`, `docs/base/modules/`,
  `docs/base/conventions/`, then inspect the actual source code.
- New feature task → read `docs/README.md`, relevant base features, then the
  target spec under `docs/custom/features/<feature>/` (if it exists).

## Documentation tree

```text
docs/
├── README.md                          ← this file (master index)
├── architecture/                      ← system-level knowledge
│   ├── overview.md                    ← stack, layered architecture, v1 modules
│   ├── decisions/                     ← ADRs (architecture decisions)
│   │   ├── README.md                  ← ADR index + conventions
│   │   ├── ADR-0001-adminlte-template.md
│   │   ├── ADR-0002-rbac-spatie.md
│   │   ├── ADR-0003-sanctum.md
│   │   ├── ADR-0004-soft-delete-audit.md
│   │   ├── ADR-0005-dark-mode.md
│   │   ├── ADR-0006-single-tenant.md
│   │   ├── ADR-0007-email-verification.md
│   │   ├── ADR-0008-template-sidebar.md
│   │   ├── ADR-0009-feature-flags.md
│   │   └── ADR-0010-route-level-authz.md
│   └── decisions/
├── base/                              ← CURRENT IMPLEMENTED SYSTEM
│   ├── README.md                      ← system scope + PRD summary
│   ├── features/                      ← per-feature docs
│   │   ├── auth.md
│   │   ├── rbac.md                    ← authorization
│   │   ├── audit-trail.md
│   │   ├── feature-flags.md
│   │   ├── i18n.md
│   │   ├── licensing-and-billing.md
│   │   ├── notifications.md
│   │   ├── api-tokens.md
│   │   ├── api-mobile.md
│   │   ├── license-mode-design.md
│   │   ├── permission-sync-design.md
│   │   └── plan-limits-design.md
│   ├── modules/                       ← layer/module reference
│   │   ├── api.md
│   │   ├── backend.md
│   │   ├── frontend.md
│   │   └── infrastructure.md
│   └── conventions/                   ← mandatory coding rules
│       └── coding.md
├── custom/                            ← PROPOSED/NEW features (per derived project)
│   ├── README.md
│   ├── _template/
│   │   └── feature.md
│   └── features/                      ← future custom feature specs
├── agents/                            ← guidance for AI agents consuming docs
│   ├── README.md
│   └── rules.md                       ← context-loading + coding rules
├── CHANGELOG.md                        ← general-fixes changelog
└── CONTRIBUTING.md                      ← dev setup + open items
```

> `docs/base/` = **reality** (implemented). `docs/custom/` = **intent** (not yet built).
> Each base feature file declares its **status** at the top.

## Quick links

- [Architecture overview](./architecture/overview.md)
- [Stack + modules (locked decisions)](./architecture/overview.md#stack-locked)
- [Authorization model — route-level `can:` + `feature:`](./base/features/rbac.md)
- [Coding standard (mandatory)](./base/conventions/coding.md)
- [Licensing & billing (Model 1, dummy PG)](./base/features/licensing-and-billing.md)
- [i18n (en/id, file→DB override)](./base/features/i18n.md)
- [ADR-0010: gate on route, not controller](./architecture/decisions/README.md)
- [ADR index](./architecture/decisions/README.md)
- [Agent rules](./agents/rules.md)
