---
id: BASE-007
name: Licensing & Billing
status: implemented
---

# Licensing, Plans & Billing — Design & Flow

Status: DESIGN (not yet implemented). Current model = **Model 1 (per-client license)**,
with a seam for migration to **Model 2 (hub SaaS multi-tenant)** later.

## 1. Three concerns, kept separate (do not merge)

| Concern | Responsibility | Read by |
|---|---|---|
| **Entitlement** | Limits (max members/projects) + which features are active | App on every access check |
| **License** | Key that unlocks a paid plan (code) | Instance activation |
| **Billing** | Collecting money (PG webhook, invoice, recurring) | At payment time |

Separate these from the start. Entitlement does NOT know where the plan came
from (license key or payment). This is what makes the Model 1 → Model 2
migration cheap.

## 2. Distribution models

### Model 1 — License per client (NOW)
- 1 company = 1 instance. Source stays with the developer (not handed to client).
- Client gets a `license_key` → unlocks plan X. Active plan stored in `settings`.
- Entitlement scope = **instance** (singleton). `Plan::for(null)`.

### Model 2 — Hub SaaS (LATER)
- 1 site, many companies log in, each company = tenant, data scoped by `workspace_id`.
- Entitlement scope = **tenant**. `Plan::for($tenant)`.
- The expensive part of Model 2 is NOT the plan system, but row-level
  `workspace_id` scoping across all tables + a Tenant resolver. That is what
  must be deferred, not the plan system.

**The seam (core):** every check goes through `Plan::for($scope)`. Model 1
`$scope=null`, Model 2 `Plan::for($tenant)`. The plan-check logic is identical,
only the scope source changes.

## 3. Data model (Model 1)

```sql
plans
  id, slug (unique, CUSTOM — any string, e.g. 'starter-2026'), name,
  price_monthly decimal(12,2), is_active bool,
  limits  json,   -- {"max_members":N,"max_roles":N,"max_permissions":N,"max_features":N,"max_storage_mb":N,"allowed_permissions":[...]}  (see Plan::LIMIT_KEYS)
  features json    -- custom list, e.g. ["kanban","audit","telescope","api","sso"]
  -- FULL CRUD: admin can create/edit/delete plans, any slug, any price,
  -- any limits/features. No fixed tier enum. 'free' is just a plan row with
  -- price_monthly = 0 (seeded by default), not a special-cased constant.

licenses
  id, plan_slug, license_key (unique), type (recurring|lifetime|manual),
  status (active|revoked|expired), issued_to,
  expires_at nullable,            -- NULL = never expires (lifetime/manual)
  created_at
  -- NON-TRANSFERABLE: issued_to is bound to the instance it was activated on;
  -- there is NO reassign/reissue-to-another-instance path.

settings
  key='active_plan' -> slug          -- plan in effect for this instance
  key='license_key' -> string        -- activated license

-- when billing (Model 1 extension / Model 2):
subscriptions
  id, plan_slug, status (active|canceled|past_due),
  gateway (midtrans|xendit), gateway_subscription_id,
  current_period_start, current_period_end, scope_id nullable  -- tenant_id in Model 2
invoices
  id, subscription_id, amount, status (paid|pending|failed), gateway_ref
webhook_events
  id, gateway, event_id unique, payload json, processed_at  -- idempotency
```

## 3b. License shape & issuance

**Shape:** a signed string code, e.g. `LIC-BASIC-8F3K2QX9P1`.
`license_key = "LIC-" . planSlug . "-" . HASH(planSlug . secret . expires_at)`.

**Who generates it:** ONE method, `LicenseService::issue(planSlug, $attrs)`,
called from two places (no second system):
- **A. Auto (recurring):** PG webhook `payment.success` → `issue(planSlug,
  ['type' => 'recurring', 'expires_at' => $periodEnd])`. Auto-extended on each
  renewal webhook.
- **B. Manual (off-market / lifetime):** admin enters it via console or UI →
  `issue(planSlug, ['type' => 'manual'|'lifetime', 'expires_at' => null])`.
  Use for: a company pays you directly (bank transfer), or you grant a
  lifetime license. `expires_at = null` ⇒ never expires.

**Why signed, not random:** verification re-hashes with `config('app.license_secret')`
and checks the hash. A client cannot forge `LIC-ENTERPRISE-XXXX` without the
secret (which ships in your code, not theirs). DB row is the record; the
signature is the tamper check.

**Status & days-left (single gate, root-cause style):**
```
status():
  if revoked                      -> 'revoked'
  if expires_at === null          -> 'active'          // lifetime / manual
  if expires_at < now()           -> 'expired'
  else                            -> 'active'
daysLeft() = expires_at?->diffInDays(now())            // null => INF (lifetime)
```
Expired or revoked ⇒ active plan falls back to `free`, paid features lock.
UI MUST show a dashboard badge with days left: "License: 12 days left" /
"Expired — downgraded to Free" / "Lifetime" (REQUIRED, not optional).
Email reminder/notifications ARE allowed (Laravel notification class; e.g.
7 days before expiry, on failed payment, on downgrade). Checked at activation
+ on request entry (cached in `settings`), not a per-second cron.

## 4. Entitlement service (seam `for($scope, $user)`)

```php
// app/Services/PlanService.php
final class PlanService
{
    // Model 1: $scope = null, $user = null. Model 2: $scope = Tenant.
    // Per-user mode: $user = resolved User (second arg).
    //
    // Production callers use PlanService::for($user) — the method auto-detects
    // a User passed as the first argument and promotes it to the $user param.
    // Tests may use the explicit form PlanService::for(null, $user).
    public static function for(?object $scope = null, ?User $user = null): self
    {
        $slug = $scope
            ? $scope->plan_slug                       // Model 2: plan per tenant
            : Setting::get('active_plan', 'free');   // Model 1: instance setting
        return new self(Plan::where('slug', $slug)->firstOrFail());
    }

    public function can(string $feature): bool
    {
        return in_array($feature, $this->plan->features, true)
            && $this->plan->is_active;
    }

    public function membersLeft(): int
    {
        $used = User::count();                        // Model 1: global. Model 2: scoped where tenant
        return max(0, ($this->plan->limits['max_members'] ?? 0) - $used);
    }
}
```

Usage: `Plan::for(user($user))->can('kanban')`, `Plan::for(null)->membersLeft() === 0`.
One gateway → root-cause style, not checking inside every controller.

### 4.1 Per-user mode resolution
```
User → License (status=active, not expired) → License.plan_slug → Plan
```
When `license_mode = per_user`, `PlanService::for($user)` detects the User
argument and resolves the user's active license. If no active license exists,
falls back to `Setting::get('default_plan', 'free')`.

`PlanService::for($user)` works because the method auto-detects a User passed
as the first argument — callers do NOT need to know about the two-argument form.

### 4.2 License key format (per-user)
```
license_key = "LIC-" . planSlug . "-" . HASH(planSlug . "|" . expires . "|u" . userId . secret)
```
Global licenses omit the `|u{userId}` segment — backward compatible with the
old key format. `LicenseService::verify()` includes `user_id` when
regenerating the key for per-user licenses.

## 5. Flow

### License activation (Model 1 global, no PG)
```
admin enters license_key in UI/console
  -> LicenseService::activate(key)
       -> verify signature (re-hash with license_secret) + row exists
       -> settings.active_plan = plan_slug
       -> settings.license_key = key
  -> plan applies instantly, features gated via PlanService::for()
```

### License activation (per-user mode)
```
LicenseService::activate(key, issuedTo, forUser)
  -> finds license row by key + user_id
  -> verifies signature (key includes |u{userId})
  -> sets status='active' on that user's license ONLY
  -> does NOT touch settings.active_plan / settings.license_key
  -> does NOT revoke other users' licenses
```

### License issuance — two paths, one method
```
-- A. Auto (recurring): PG webhook payment.success
   BillingService::handleWebhook()
     -> verify signature + idempotency
     -> LicenseService::issue(planSlug, ['type'=>'recurring','expires_at'=>$periodEnd])
     -> settings.active_plan = planSlug

-- B. Manual (off-market / lifetime): admin console or UI
   LicenseService::issue(planSlug, ['type'=>'manual'|'lifetime','expires_at'=>null])
     -> returns signed code, admin gives it to client
     -> client activates via path above
```
Both callers use the same `issue()` — no duplicate system. Lifetime/manual
just set `expires_at = null`.

### Upgrade via billing (Model 1 extension / Model 2)
```
user picks plan -> BillingService::checkout(plan)
  -> call PG (Midtrans Snap) -> get snap_token
  -> redirect to PG
  -> PG webhook -> BillingService::handleWebhook(payload)
       -> verify signature
       -> idempotent via webhook_events.event_id
       -> create/renew subscription + invoice paid
       -> settings.active_plan = plan (or tenant.plan_slug in Model 2)
```

### Limit check on create
```
controller create member/project:
  if (Plan::for()->membersLeft() === 0) abort(402, 'Limit reached');
```

## 6. Payment gateway — recommendation (Indonesia, data Jul–Aug 2026)

MDR per method (excl. 11% VAT):

| Gateway | VA | QRIS | E-wallet | Card | Monthly | Notes |
|---|---|---|---|---|---|---|
| **Midtrans** | Rp4.000 flat | 0.7% | ~1.5% | 2.9%+Rp2.000 | – | Native GoPay, Snap drop-in, PCI-DSS, settle H+3 |
| **Xendit** | ~0.5%+flat | 0.7% | 2.70% | 2.9%+Rp2.000 | Rp25.000/sub-acct | Clean API, top-tier disbursement, settle 7d |
| **iPaymu** | Rp3.5K (cheapest) | 0.7% | ~ | ~ | – | KTP-only, SME, settle H+0–H+3 |
| **Tripay** | low | 0.7% | | | – | KTP-only, fast verify |
| **Doku** | custom | 0.7% | | custom | – | Enterprise, 6 BI licenses |

**(All QRIS = 0.7% because regulated by Bank Indonesia — identical across gateways.)**

### Choice
- **Primary: Midtrans.** Cheapest for collect-only, most popular in Indonesia,
  secure (PCI-DSS, part of GoTo), native GoPay (Indonesian buyers expect it),
  Snap drop-in makes integration fast, no setup/monthly fee. Recurring/
  subscription supported → fits a subscription SaaS.
- **Secondary (if needed): Xendit.** Cleaner API and dev-friendly docs, and
  first-class disbursement (paying out) if the business model ever needs
  payouts. Pick this if the team prefers a custom API over Snap.
- **Never** capture card data yourself. Always use the Snap redirect / PG page.
- **Sandbox / dummy endpoint for dev.** Midtrans provides a Sandbox mode
  (separate keys, `is_production=false`) with dummy VA / QRIS / card numbers
  that always succeed (and ones that fail) — no real money. Use this for the
  entire development phase; never hit production keys in dev. (§10.14 env
  isolation.) If a PG is not yet connected, `BillingService::checkout()` can be
  short-circuited behind a `billing.fake` flag that issues the license
  directly — keeps dev usable without any PG.
- **Free tier.** Yes — `free` is just a plan row with `price_monthly = 0`,
  seeded by default and always active. No PG interaction needed for free; the
  client runs on it out of the box. Paid plans are the only ones that touch the
  PG.

### Laravel integration
- Midtrans has no official Laravel Cashier driver (Cashier = Stripe/Paddle).
  → use the official SDK `midtrans/midtrans-php` or REST directly, wrapped in
  **1 BillingService** + **1 signed webhook controller**. No extra billing
  package needed. (Ponytail: don't pull in Cashier just for Stripe when the
  Indonesian market uses Midtrans.)

## 7. Security (required)
- Webhook: verify PG signature/HMAC, reject on failure.
- Idempotency: `webhook_events.event_id` unique → no double-processing.
- Do not store card data. Midtrans/Snap holds it.
- License key: hash in DB (not plaintext), validate via signature, not just
  string match.
- Enforce limits on the server (controller), not only in the UI.

## 8. Migration to Model 2 (notes, not yet built)
1. Add `tenants` + `workspace_id` to tables needing isolation.
2. Tenant resolver (middleware/subdomain) → fills `$scope`.
3. `Plan::for($tenant)` reads `tenant.plan_slug` (not instance setting).
4. Billing `subscriptions.scope_id = tenant_id`.
Plan-check logic & Entitlement service UNCHANGED.

## 9. Decisions still pending from user (not locked)
- Limit ENFORCE hard (reject create) vs SOFT warning?
- PG needed now, or start Model 1 without PG (manual/lifetime license only)?
- Which plans & exact limits/features per tier (free/basic/pro/enterprise)?

## 9b. Locked decisions (from user)
- **Dashboard days-left badge: REQUIRED.** The license status + remaining days
  must render as a badge on the dashboard (see §3b). Not optional.
- **Email notifications: ALLOWED.** Reminders (e.g. 7 days before expiry, failed
  payment, downgrade) may be sent via a Laravel notification class.
- **License is NON-TRANSFERABLE.** `issued_to` is bound to the instance it was
  activated on. No reassign / reissue-to-another-instance mechanism exists
  (see `licenses` note in §3). A client moving to a new server must be issued a
  fresh license by the developer.
- **Plans are FULLY CUSTOM (CRUD).** slug, price, limits, features are all
  admin-editable — no fixed tier enum. `free` is just a plan row with
  price_monthly = 0, seeded by default.
- **NO refunds.** Refund flow (§10.15) is out of scope. License revocation
  (§10.7) may still happen on abuse, but there is no refund path or refund
  trigger on the invoice.

## 10. Open concerns (best-practice, business-variable)

These are NOT blockers for the design, but must be locked before building
`LicenseService` / `BillingService`. Business designs differ per client, so
each is left as a hook, not hardcoded.

1. **License tamper resistance (REQUIRED before LicenseService).**
   In Model 1 the client owns the DB, so they can edit `settings.active_plan`
   directly. Mitigation: `Plan::for()` must re-verify the license signature
   (re-hash with `license_secret`) on every check, not just read the setting.
   Store the license hash in `settings`; if it does not match a valid,
   non-expired, non-revoked `licenses` row → fall back to `free`. Never trust
   the raw `active_plan` string.

2. **Grace period & downgrade experience.**
   On expiry: hard-lock paid features immediately, or grant a grace window
   (e.g. 7 days, banner only)? On downgrade below current usage: freeze the
   excess data (projects/members), do NOT auto-delete it. Best practice =
   freeze, never delete customer data silently.

3. **Trial.**
   Is `free` itself the trial, or is there a separate time-boxed full-feature
   trial? If separate, add `trial_ends_at` to `licenses` and gate on it. Not
   covered by the current `plans` model — add only when a trial is needed.

4. **Plan change & proration.**
   Mid-cycle upgrade: charge full or prorated? Downgrade: immediate or at
   period end? Midtrans does not prorate automatically — the math lives in
   `BillingService`. Expose an `onPlanChange(Plan $old, Plan $new)` hook; do
   not hardcode pricing rules in the controller.

5. **Per-instance vs per-seat pricing.**
   Entitlement limits are per-instance (`max_members`). Billing may instead be
   per active seat. This does NOT change the entitlement seam — only the
   billing math in `BillingService` (count active users × seat price). The
   `Plan::for($scope)` seam already covers both; decide the pricing model
   when wiring billing, not in the entitlement layer.

6. **Renewal failure / dunning (recurring only).**
   A recurring charge can fail each cycle (expired card, insufficient funds).
   The `subscriptions` status must support `past_due`, with a retry sequence
   and a banner "payment failed — features downgrade to Free after N days".
   Without this, a client whose payment fails keeps paid features. Midtrans
   does not auto-dunning; the retry + grace logic lives in `BillingService`.

7. **License revocation / refund.**
   On refund or abuse, set `licenses.status = revoked` (soft, never delete the
   row — keep history) and immediately lock features. Record a reason in an
   audit column. Revocation is the explicit path that flips `Plan::for()` to
   `free`; it must be instant, not wait for expiry.

8. **Plan catalog versioning / snapshot.**
   Plan `limits`/`features` will change over time (price rise, feature moves).
   A client who already paid must keep what they bought. Snapshot the plan at
   issue time: store `plan_version` on the license, or copy `limits`/`features`
   into the `licenses` row. `Plan::for()` reads the snapshot, not the live
   `plans` row — otherwise a mid-term price/limit change silently narrows a
   paying client's entitlement.

9. **Concurrent activation / race (Model 1).**
   Two admins activating, or a client restoring an old DB after upgrade, can
   yield two active plans or a "ghost" plan. Enforce at most ONE active license
   per scope (unique `status=active` per instance/tenant) and make the
   activate + `settings.active_plan` write atomic (DB transaction).

10. **Audit & compliance trail.**
    Who issued/revoked/activated a license, when, from which IP — needed for
    disputes ("I paid but I'm locked") and tax proof. Reuse the existing
    `activity_log`; do not build a separate table.

11. **Webhook replay / clock skew.**
    A PG may send a webhook twice or late (server clock drift). Idempotency via
    `webhook_events.event_id` (§3) is required. Also: never overwrite
    `expires_at` from a webhook without checking `event_period_end >
    stored_period_end` — a replay must not clobber a newer renewal.

12. **Localization & tax (PPN 11%).**
    `plans.price_monthly` is pre-tax; Indonesian invoices must show PPN 11%
    separately. Store `invoices.amount` + `vat` (amount already excludes PG
    MDR, which is taxed separately by the PG). Never mix taxable vs non-taxable
    amounts in one column.

13. **Customer-facing billing portal.**
    Users must view invoices, history, download PDF, update payment method,
    cancel. Prefer the PG's customer dashboard where sufficient; otherwise a
    minimal blade reading `invoices`. Without self-service, support tickets
    spike.

14. **Environment isolation (sandbox vs production).**
    Drive PG mode via env (`midtrans.is_production` + separate keys). A sandbox
    webhook must NEVER update a production plan. Never hardcode the mode.

15. **Refund / chargeback handling — OUT OF SCOPE (user: no refunds).**
    Revocation on abuse still applies (§10.7), but there is no refund path,
    no `refunded` invoice state, and no refund trigger. Chargeback is also not
    handled (Indonesian card share is small; if ever needed, treat as revoke).

16. **Rate limit / abuse on activation & checkout.**
    Throttle + CSRF the activate and checkout endpoints (Laravel `throttle`
    middleware). Activation is brute-forceable (clients guess keys) — limit
    attempts and lock after N failures.

### Common third-party PG failure modes (handle by design)
- **Signature verify fails** → wrong key or env mismatch. Verify against the
  configured production/sandbox key; reject on mismatch.
- **Webhook replay** → §10.11 idempotency.
- **Out-of-order / late webhook** → §10.11 period-end guard.
- **Settlement delay** → do not grant features on `pending`; wait for the PG
  `settlement` status before activating the plan.
- **Currency mismatch** → Midtrans is IDR-only; reject any non-IDR payload.
- **Sandbox hitting production** → §10.14 env isolation.

## 12. Billing portal + admin analytics (built)

Full portal, not PG-only (user decision: host the app, control everything,
track revenue + active users).

### 12.1 User portal — `/billing` (gated `feature:billing` + login)
- `billing.index`: current plan + active license (key/type/expiry), payment history (paginated), download invoice PDF.
- `billing.checkout`: existing dummy checkout (subscriber picks a paid plan).
- `billing.cancel`: `BillingCancelRequest` (`authorize: billing.cancel`) →
  `BillingService::cancelUser()` = **revoke license + freeze payment** (NO delete, NO refund — §10.2). Frozen data stays for accounting.
- `billing.invoice/{payment}`: PDF via `barryvdh/laravel-dompdf` (blade-based, flexible). Owner-only (`payment.user_id === auth()->id()`).

### 12.2 Admin analytics — `/admin/billing` (gated `can:billing.view` + `feature:billing`)
KPIs: revenue (sum paid), active subscribers (users with an active license),
paid-this-month, plan breakdown (active licenses per plan). Plus tables:
recent payments (with user) + active licenses (with user/expiry).

### 12.3 Data model
- `Payment`: +`invoice_no` (lazy `INV-{id}`), +`canceled_at`, `user_id` FK.
- `License`: +`user_id` FK (per-subscriber ownership; scope `active()` = active + not expired).
- `User`: `payments()`, `licenses()`, `hasActiveLicense()`.
- Permissions: `billing.view` (admin), `billing.cancel` (user). Seeded.

### 12.4 Notes
|- `License`: +`user_id` FK (per-subscriber ownership; scope `active()` = active + not expired). Per-user mode uses this for plan resolution (see §4.1).
|`- `User`: `payments()`, `licenses()`, `license()` (active hasOne), `isSuperAdmin()`.
- Invoice is dummy (no real PG); swap `BillingService::checkout` for Midtrans
  when `billing.fake=false` — PDF + portal unchanged.

## 11. Plan limit model (implementation reference)

The `plans` row carries a `limits` JSON + `features` array. This is the single
source the admin UI, `PlanService`, and the license snapshot all read.

### 11.1 `limits` keys (see `App\Models\Plan::LIMIT_KEYS`)
- `max_members`, `max_storage_mb`, `max_roles`, `max_permissions`, `max_features` — numeric caps.
- `allowed_permissions` — array of `permissions.name` a subscriber may assign when
  creating roles. Empty = allow any permission of enabled features.
|+ **Role creation is gated by the `role.create` permission** (Form Request
  `authorize()`), NOT by a `can_create_roles` field. Role creation capability
  flows from the `roles` **feature**: plans with the `roles` feature have
  `role.create` in their `allowed_permissions`; `syncPermissionsForPlan()` is
  a no-op (see `docs/base/features/permission-sync-design.md`), so `role.create`
  is NOT auto-assigned to roles. Instead, the super-admin/admin role is seeded
  with `role.*` permissions directly (Challenge 3 exempts `role.*` from the
  Plan boundary Gate check). Free plan (`features=[]`) → no `roles` feature →
  `role.create` not in `allowed_permissions` → `filterPermissions` blocks assignment
  → subscribers forbidden.

### 11.2 `features` + permission mapping
- `features` is a subset of `config('pennant.features')` slugs.
- Permission → feature mapping is **dynamic**, derived from the pennant config
  (`App\Models\Permission::featureOf()`). The permission prefix (singular, e.g.
  `user.view` → `user`) is pluralized (`Str::plural`) and matched against a feature
  slug (`users`). Adding a feature to `config/pennant.php` auto-maps its
  `*.{action}` permissions — no hardcoded list to maintain.

### 11.3 Server guards (anti-bypass)
`App\Http\Requests\Plan\PlanRequest` validates, after field rules:
- `count(features) <= max_features` (else `plans.feature_limit_exceeded`).
- every `allowed_permissions.*` must belong to an enabled feature, i.e.
  `featureOf(perm)` ∈ `features` (else `plans.permission_feature_mismatch`).
Client-side disables extra feature checkboxes and hides permission groups for
disabled features, but the request rules are the real gate.

### 11.4 Authorization
Plan routes are gated route-level (`routes/web.php` → `can:feature.manage`);
the Form Request `authorize()` re-checks the same permission. Role creation
for subscribers is gated by the `role.create` permission, granted via the
`roles` plan feature — see §11.1.
**Super-admin bypass**: super-admin (user holding the `super-admin` Role)
bypasses Plan permission AND Plan feature entitlement in the Gate `before`
callback via `BypassService::isSuperAdmin()`. Pennant is NOT bypassed (checked
at route middleware). See ADR-0011 and `docs/base/features/rbac.md`.
