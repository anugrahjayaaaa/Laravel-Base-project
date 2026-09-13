# Base Features

Implemented features of the Laravel Base Project. Each file declares a **status**
frontmatter field at the top (`implemented` / `partially-implemented` /
`deprecated`).

| Feature | Status | ID |
|---------|--------|----|
| [Auth](auth.md) | implemented | BASE-002 |
| [RBAC (authorization model)](rbac.md) | implemented | BASE-003 |
| [Audit Trail](audit-trail.md) | implemented | BASE-004 |
| [Feature Flags](feature-flags.md) | implemented | BASE-005 |
| [i18n (en/id)](i18n.md) | implemented | BASE-006 |
| [Licensing & Billing](licensing-and-billing.md) | implemented | BASE-007 |
| [Notifications](notifications.md) | implemented | BASE-008 |
| [API Tokens](api-tokens.md) | implemented | BASE-009 |
| [API (mobile)](api-mobile.md) | implemented | BASE-010 |
| [License Mode Design](license-mode-design.md) | design | BASE-011 |
| [Permission Sync Design](permission-sync-design.md) | design | BASE-012 |
| [Plan Limits Design](plan-limits-design.md) | design | BASE-013 |

## Design notes

- `license-mode-design.md`, `permission-sync-design.md`,
  `plan-limits-design.md` document the design rationale behind the
  implemented licensing/billing feature; they are **design** status (rationale,
  not new work).
