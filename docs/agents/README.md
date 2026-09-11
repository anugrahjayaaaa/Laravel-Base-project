# AI Agent Guidance

How Hermes and other AI agents should consume this documentation.

## Context-loading rules

### Existing-feature task
Read, in order:
1. [`docs/README.md`](../README.md) — master index
2. [`docs/base/README.md`](../base/README.md) — system scope
3. The relevant feature under `docs/base/features/<feature>.md`
4. Related module under `docs/base/modules/`
5. [`docs/base/conventions/coding.md`](../base/conventions/coding.md)
6. Actual source code (the docs must match code; if conflict, investigate)

### New-feature task
Read, in order:
1. [`docs/README.md`](../README.md)
2. Relevant base features (the new feature likely reuses them)
3. [`docs/architecture/overview.md`](../architecture/overview.md)
4. [`docs/base/conventions/coding.md`](../base/conventions/coding.md)
5. The target spec under `docs/custom/features/<feature>/` (if exists)

Then determine implementation impact.

### Verification checklist (before any change)
- [ ] Does this touch a **base** feature or **custom** feature?
- [ ] Does a base feature file need a **status** update?
- [ ] Does a base behavior change require a new **ADR**?
- [ ] Does the code match the docs? (if not, fix the docs, not the assumption)

## Rules

- `docs/base/` = implemented reality. Never treat proposed/custom specs as
  implemented.
- `docs/custom/` = intent. Proposed features are NOT available until their
  status is `implemented`.
- Do NOT load the entire docs tree — load by feature/module/convention only.
- Authorization is route-level (`can:` + `feature:`). Never in a controller
  `__construct()`.
- Validation lives in Form Requests, never inline `$request->validate()`.
- i18n: `ui()` calls `ui.php` keys; `__('messages.*')` calls `messages.php`.
  Never mix. Never hardcode English in Blade.

See [rules.md](./rules.md) for the full coding + conventions checklist.
