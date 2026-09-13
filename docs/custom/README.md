# Custom Features

`docs/custom/` holds **proposed or new** feature specifications for derived
projects. These documents represent **intent**, NOT yet-implemented behavior.

> Custom features are NOT part of the base system until their status is
> `implemented` AND the feature is migrated to `docs/base/features/`.

## Status values

`proposed` → `approved` → `in-progress` → `implemented` → `deprecated`
(rejected features may skip from `proposed`).

## Templates

Start a new custom feature from [`_template/feature.md`](./_template/feature.md).
It defines the standard structure: Overview, Problem, Goal, Non-Goals, Actors,
Functional/Non-Functional Requirements, Business Rules, Main/Alt/Error Flows,
Technical Design, Backend/Frontend/Database/API Impact, Security, Dependencies,
Risks, Testing Strategy, Acceptance Criteria, Open Questions, Implementation Notes.

## Custom features

Individual specs live under [`features/`](./features/). Add new feature
folders as work is requested.

## Relationship to base

Custom features describe extensions to the base system. They MAY depend on base
features ([`../base/features/`](../base/features/)) — reference them, do not
duplicate. When a custom feature is implemented, it graduates into
`docs/base/features/`.