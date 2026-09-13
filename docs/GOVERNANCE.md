---
id: DOC-GOV-001
name: Documentation Governance Policy
type: policy
status: active
---

# Documentation Governance Policy

This policy governs how Hermes (AI agents + human developers) maintain this
repository's documentation. It is the binding contract between documentation
and reality.

## Authority hierarchy

1. **Source code** (primary)
2. **Database schema / migrations**
3. **Tests** (Pest, PHPStan, Pint)
4. **CI/CD definitions** (`.github/workflows/`)
5. **`base/` documentation** (describes implemented behavior)
6. **`custom/` documentation** (proposed/intent — never treated as implemented)
7. **ADR** (records why, not what)

When any lower layer conflicts with a higher one, the higher wins. Fix the
docs, not the assumption.

## Quality gates (must pass before docs work is "done")

| # | Gate | How verified |
|---|------|--------------|
| 1 | Correctness | docs match source code |
| 2 | Completeness | FRs/NFRs/BRs/ACs present where required |
| 3 | Consistency | no contradictory statements across docs |
| 4 | Security | no secrets (scan: `scripts/security_check`) |
| 5 | Links | 0 broken internal links (`scripts/link_check`) |
| 6 | Status clarity | every base feature has frontmatter `status:` |
| 7 | ADR coverage | architectural decisions recorded as ADRs |
| 8 | AI readability | stable IDs, plain headings, short sections |
| 9 | Testability | requirements traceable to test files |
| 10 | Simplicity | no invented behavior; no speculative content |

## Audit tooling

Single script: `docs/scripts/doc-audit.py`, subcommands:

| Command | Gate checked | Exits non-zero on |
|---------|-------------|--------------------|
| `links` | 5 (broken links) | broken internal links |
| `security` | 4 (secrets) | secret-like strings |
| `status` | 6 (status) | base feature missing `status:` frontmatter |
| `ids` | 2 (requirement IDs) | custom feature missing FR/NFR/BR/AC |
| `all` | 1-10 | any failure |

Run before commit (from repo root):

```bash
python3 docs/scripts/doc-audit.py all
```

## Drift detection

On every feature task touching existing functionality:

1. Compare the feature doc against the route/controller/Model.
2. If the doc mentions behavior the code does not have → mark `Needs Verification`.
3. If the code does behavior the doc does not mention → add a doc edit to the diff.
4. If the mismatch is an intentional architectural decision → file an ADR.

## Change rules

1. Identify affected docs.
2. Verify actual implementation.
3. Determine: change current-state (`base/`) or proposed-state (`custom/`).
4. Update ONLY relevant documents.
5. Validate consistency, security, links.

## New feature lifecycle (documentation)

```text
Requirement → custom/features/<name>/ (spec) → Implementation → ADR → base/features/<name>/ (reality)
```

A feature moves from `custom/` to `base/features/` ONLY when implementation is
complete and verified. The custom spec is NOT deleted — it becomes the
design record retained as `base/features/<name>/design.md` if it exists.

## Ponytail / minimal-diff enforcement

This repository operates under **Ponytail full-mode** as the working
default. The shortest correct diff wins over a broader redesign. Before any
UI/docs change ask: (1) does this fix need to exist at all? (2) is there an
existing pattern to reuse? Only then ship minimal code.

For UI audit findings: cosmetic-only issues without functional/security impact
are recorded as `Needs Decision` and **not fixed** — over-fixing is a defect
equal to a bug. Functional gaps (themed error pages, broken authz at route
level) are auto-fixed.

## What this does NOT do

- Does NOT mandate requirement IDs on every base feature doc (only where
  the feature has measurable business rules). For existing features, status
  + architecture sections are sufficient.
- Does NOT require Mermaid diagrams on every flow (only where a diagram
  improves understanding over numbered steps).
- Does NOT require ADRs for trivial implementation details.

## Violation handling

- Docs work that fails a quality gate is flagged `Needs Verification` and
  reported — not silently "fixed".
- When uncertainty exists, use `Unknown` or `Needs Verification` verbatim,
  never invent.
