# Remediation Tracker

## Status Legend

* `[ ] OPEN` — not started
* `[~] IN PROGRESS` — currently being worked on
* `[x] FIXED` — implementation completed, verification pending
* `[✓] VERIFIED` — implementation and verification completed
* `[-] DEFERRED` — intentionally postponed

## Rules

1. Work on one task at a time unless tasks are explicitly independent.
2. Do not skip task IDs.
3. Do not mark a task `VERIFIED` until the fix has been tested and verified.
4. Keep task IDs stable; never rename or reuse an existing ID.
5. When a new issue is discovered, add a new task ID instead of silently changing an existing task.
6. Keep API, Plan/License, Billing/Payment, and CI/CD tasks deferred until all higher-priority phases are completed.
7. Detailed findings, affected files, root cause, fix notes, tests, and QA notes should be added under the corresponding task when work begins.

---

# Phase 1 — Authentication

* [ ] AUTH-01 — Fix authentication flow
* [ ] AUTH-02 — Fix account lock / unlock enforcement
* [ ] AUTH-03 — Fix password change / reset flow
* [ ] AUTH-04 — Fix email verification flow

# Phase 2 — Authorization / RBAC

* [ ] RBAC-01 — Fix permission naming / consistency
* [ ] RBAC-02 — Fix User authorization matrix
* [ ] RBAC-03 — Fix Role authorization matrix
* [ ] RBAC-04 — Fix Permission authorization matrix
* [ ] RBAC-05 — Fix IDOR / ownership authorization
* [ ] RBAC-06 — Fix soft-deleted role / permission authorization
* [ ] RBAC-07 — Verify superadmin bypass behavior

# Phase 3 — User & Profile

* [ ] USER-01 — Fix User CRUD lifecycle
* [ ] USER-02 — Fix User role assignment lifecycle
* [ ] USER-03 — Fix User delete / restore lifecycle
* [ ] USER-04 — Fix Profile update flow

# Phase 4 — Session & Security

* [ ] SESSION-01 — Fix web session lifecycle
* [ ] SESSION-02 — Fix logout-others behavior
* [ ] SECURITY-01 — Audit sensitive-data exposure
* [ ] SECURITY-02 — Audit mass assignment / validation boundaries

# Phase 5 — Feature Flags

* [ ] FEATURE-01 — Fix Pennant feature enforcement
* [ ] FEATURE-02 — Fix feature + permission interaction
* [ ] FEATURE-03 — Verify superadmin vs feature flag behavior

# Phase 6 — Audit & Observability

* [ ] AUDIT-01 — Fix audit actor consistency
* [ ] AUDIT-02 — Fix audit coverage for mutations
* [ ] AUDIT-03 — Fix sensitive data exclusion from audit
* [ ] AUDIT-04 — Verify audit query / filter behavior

# Phase 7 — Settings & Notifications

* [ ] SETTING-01 — Fix Settings validation architecture
* [ ] SETTING-02 — Verify Settings authorization
* [ ] NOTIFY-01 — Verify notification lifecycle
* [ ] NOTIFY-02 — Verify notification backfill / cleanup

# Phase 8 — i18n & Data Integrity

* [ ] I18N-01 — Fix translation inconsistencies
* [ ] I18N-02 — Verify EN / ID coverage
* [ ] DB-01 — Verify Model ↔ Migration consistency
* [ ] DB-02 — Verify relationships / foreign keys / indexes
* [ ] DB-03 — Verify casts / fillable / nullable consistency

# Phase 9 — Code Quality & Architecture

* [ ] CODE-01 — Fix controller / request / service boundary issues
* [ ] CODE-02 — Fix duplicated / inconsistent patterns
* [ ] CODE-03 — Apply Pint cleanup
* [ ] CODE-04 — Review strict-types policy
* [ ] ARCH-01 — Final architecture consistency review

# Phase 10 — Full Verification

* [ ] QA-01 — Regression test all fixed areas
* [ ] QA-02 — Manual feature-chain verification
* [ ] QA-03 — Authorization matrix verification
* [ ] QA-04 — Security regression verification
* [ ] QA-05 — Final documentation / behavior consistency check

# Deferred — Last Priority

* [-] API-01 — API authorization / security
* [-] API-02 — API session / token behavior
* [-] PLAN-01 — Plan architecture
* [-] LICENSE-01 — License lifecycle
* [-] BILL-01 — Billing / payment flow
* [-] CICD-01 — CI/CD pipeline

---

# Task Detail Template

Use this section format when a task is actively investigated.

## AUTH-01 — Fix authentication flow

Status: OPEN
Priority: P0
Dependencies: None

### Finding

Pending investigation.

### Root Cause

Pending investigation.

### Affected Files

Pending investigation.

### Fix

Pending implementation.

### Tests

Pending.

### Manual QA

Pending.

### Verification

Pending.

### Notes

---

# Change Log

| Date       | Task    | Status | Summary         |
| ---------- | ------- | ------ | --------------- |
| YYYY-MM-DD | AUTH-01 | OPEN   | Initial tracker |
