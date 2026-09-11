---
id: CUSTOM-000
name: <Feature Name>
type: custom
status: proposed
---

# <Feature Name>

> Custom feature for `<Project Name>`, built on Laravel Base Project.
> Copy this to `docs/custom/features/<feature-name>/README.md` and fill in.

## Overview

One-paragraph summary of what this feature does.

## Problem

What pain point this solves, and why it is NOT part of the base system
(i.e. why it is project-specific).

## Goal

What success looks like for this feature.

## Non-Goals

Explicitly out of scope (e.g. "does NOT change auth", "no mobile API").

## Actors

| Role | Interaction |
|------|-------------|
| `<role>` | `<what they do>` |

## Functional Requirements

| ID | Requirement |
|----|-------------|
| FR-001 | … |

## Non-Functional Requirements

| ID | Requirement |
|----|-------------|
| NFR-001 | … |

## Business Rules

| ID | Rule |
|----|------|
| BR-001 | … |

## Main Flow

1. …
2. …

## Alternative Flow

e.g. "AF-1: …"

## Error Flow

e.g. "EF-1: … on validation failure, return 422."

## Technical Design

Architecture, components, responsibilities, data flow, integration points,
failure scenarios. Reference base features this reuses (e.g. `docs/base/features/rbac.md`).

## Backend Impact

New/changed models, migrations, controllers, services, routes.

## Frontend Impact

New/changed views, assets, Blade directives.

## Database Impact

| Table | Action | Notes |
|-------|--------|-------|
| `…` | new/changed | … |

## API Contract

| Method | URI | Controller@action | Gate |
|--------|-----|-------------------|------|
| GET | `/…` | `…Controller@index` | `can:…` |

## Security

Threat considerations, required permissions, secrets handling, CSRF, rate
limits. Never include actual secret values.

## Dependencies

- Base feature: `docs/base/features/<name>.md`
- Packages: (only new ones; prefer base-installed)
- External services: (none or describe)

## Risks

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| … | … | … | … |

## Testing Strategy

`tests/Feature/…Test.php` covers: …

## Acceptance Criteria

- [ ] …
- [ ] …

## Open Questions

- …

## Implementation Notes

Migration path, open questions, simplifications.
