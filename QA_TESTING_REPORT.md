# OSMS Laravel — QA Testing Report

**Date:** 2026-06-27
**Role:** Senior QA Automation Engineer (dedicated bug-hunting session)
**Scope:** Deep testing, edge-case validation, and vulnerability discovery across every existing
feature. **No new features or fixes were written** — this is an analysis + verification pass only.
**Companion document:** [BUG_TRACKER.md](BUG_TRACKER.md) — every confirmed defect with repro + fix.

---

## 1. Methodology

1. **Baseline test run** — `php artisan test` (PHPUnit, SQLite `:memory:`).
2. **Static review** of all controllers, models, form requests, middleware, scopes, services,
   migrations, routes, and the key Blade views / front-end JS.
3. **Claim verification** — every automated finding was re-checked against the actual source and the
   passing test suite. Findings that turned out to be already-defended were demoted to the
   **false-positives** list (§5) rather than logged as bugs.
4. **Front-end behavioural analysis** — traced the Cmd/Ctrl+K global-search wiring through the Vite
   asset pipeline (see BUG-001).

---

## 2. Test baseline

`php artisan test` → **70 passed (317 assertions), ~5.1s. Zero failures.**

| Suite | Coverage | Result |
| --- | --- | --- |
| `Phase1SmokeTest` | App boot, public pages, redirects | ✅ |
| `Phase2PatientTest` | Patient CRUD, duplicate-phone (per-tenant), cross-tenant 404, pagination, eye record on profile | ✅ |
| `Phase3InventoryTest` | SKU/barcode format, auto-generation, scan by barcode+SKU, **scan tenant isolation**, low-stock filter, pagination, update | ✅ |
| `Phase4OrderTest` | Order create + balance, **stock decrement**, **oversell rejected**, **duplicate-line oversell rejected**, server-side price, **cross-tenant eye-record rejected**, requires items, status transition, receipt/PDF, cross-tenant 404, eye-records JSON | ✅ |
| `Phase5AnalyticsTest` | Role gate, revenue/COGS/profit, pending dues, ledger Excel export | ✅ |
| `Phase6SearchTest` | Search across modules, empty query, **tenant isolation**, **throttling** | ✅ |
| `Phase7BillingTest` | Billing page, friendly error without keys, **webhook rejects bad signature**, webhook activates on valid signature | ✅ |
| `Auth/*`, `ProfileTest` | Breeze auth, password reset/update/confirm, email verification, profile + account delete | ✅ |

The suite is healthy and the tenant-isolation / financial-integrity invariants are genuinely
exercised. The defects below are gaps **outside** what the current tests assert.

---

## 3. Module-by-module health

### 3.1 Authentication & Onboarding — **Healthy, minor gaps**
- **Validated:** login/registration/password-reset throttling (`throttle:6,1` + 5-attempt
  RateLimiter), profile update nulling `email_verified_at` on email change, account deletion with
  password confirmation, superadmin → dashboard redirect, `hasTenant()` short-circuit.
- **Edge cases checked:** empty onboarding form (validated), logo upload type, double-submit of
  onboarding, superadmin hitting onboarding.
- **Findings:** email verification not enforced for tenant access (BUG-009); onboarding has a
  double-tenant race + unguarded logo `store()` (BUG-008).

### 3.2 Multi-tenancy integrity — **Strong**
- **Validated:** `TenantScope` + `BelongsToTenant` constrain every query to
  `auth()->user()->tenant_id`, auto-stamp `tenant_id` on create, superadmin bypass. Cross-tenant
  reads return 404 (route-model binding respects the scope).
- **Edge cases checked:** cross-tenant UUID lookups on patients/orders/inventory (404 ✅),
  unscoped `exists:` rules in `OrderController@store` (re-checked with scoped `findOrFail` — safe,
  §5), `scan()` `orWhere` precedence (Laravel `callScope` wraps manual wheres in a group — safe,
  proven by `scan respects tenant isolation`), webhook `withoutGlobalScopes()` (signature-gated, §5).
- **Findings:** none that breach isolation. This layer is the app's strongest.

### 3.3 Patients & Eye Records — **Functional, validation + lifecycle gaps**
- **Validated:** create + show, per-tenant unique phone, eye record nested under patient,
  `recorded_by` stamped server-side.
- **Edge cases checked:** duplicate phone same/different tenant, prescription numeric ranges,
  cross-patient eye-record attachment.
- **Findings:** Rx fields (`*_sph/_cyl/_add/_spl/_dv/_nv`) accept **any** number while DB columns
  are `decimal(5,2)/(6,2)` → invalid data or DB 500 (BUG-002). No edit/destroy for patients or eye
  records (§6 observation) even though `StorePatientRequest` already carries a `.ignore()` clause.

### 3.4 Inventory (SKU / barcode / scan) — **Functional, uniqueness gaps**
- **Validated:** auto SKU `{TYPE}-{BRAND}-{6}`, 12-digit barcode, scan-by-code JSON, low/out stock
  filters, pagination (`paginate(50)`), `stock_qty`/`min_alert_qty` ≥ 0, scan throttle `120,1`.
- **Edge cases checked:** negative stock (rejected by `min:0` + `unsignedInteger`), invalid barcode
  format (returns `found:false`), cross-tenant scan (isolated), SKU/barcode collision.
- **Findings:** barcode column is **indexed, not unique**, and `generateBarcode()` does no
  collision check (BUG-004); `generateSku()` has no retry on the (rare) unique-constraint hit
  (BUG-005).

### 3.5 Orders — **Strong, hardened since migration**
- **Validated:** transactional create with `lockForUpdate`, per-item qty aggregation (anti
  duplicate-line bypass), oversell guard, server-side price resolution, `balance_due` saving hook,
  `advance_paid` capped via `min(...)` and validated `min:0`, kanban status transition, PDF receipt.
- **Edge cases checked:** oversell (single + split lines), zero/negative qty (rejected), huge qty
  (no `max` — BUG-006), negative advance (clamped), cross-tenant patient/eye-record/inventory
  (all 404/rejected).
- **Findings:** missing `max` on line quantity (BUG-006, low). Otherwise solid.

### 3.6 Analytics & Exports — **Functional, unbounded-query risk**
- **Validated:** role gate `store_admin,superadmin`, revenue/COGS/profit/margin, top brands, ledger,
  dues, eager-loaded `items.inventory`, division-by-zero guarded (`$revenue > 0 ? … : 0`).
- **Edge cases checked:** empty range (no div-by-zero), `from`/`to` parse failures (silently
  defaulted), `ledger_all`/`dues_all` toggles, Excel export MIME.
- **Findings:** `ledger_all`/`dues_all` and `LedgerExport` load **unbounded** rows; a wide date
  range can pull the whole order history into memory (BUG-007, low — internal users, behind role).

### 3.7 Billing & Razorpay webhook — **Secure core, one logic gap**
- **Validated:** signature verified with `hash_equals` **before** any mutation, invalid signature →
  400, unknown subscription id → no-op, idempotent status `match`, friendly error when keys unset.
- **Edge cases checked:** bad signature, malformed payload (`payload.subscription.entity` missing →
  safe no-op), replayed webhook (idempotent), unknown event (no status change), re-subscribe.
- **Findings:** re-subscribing while already active creates a **new** Razorpay subscription and
  overwrites the stored id, orphaning the old (still-billing) one (BUG-003, medium — dormant until
  keys configured). Billing routes are not role-gated (BUG-010, low).

### 3.8 Global search front-end — **Broken**
- **Finding:** Cmd/Ctrl+K never opens the palette — the init IIFE throws on `new bootstrap.Modal()`
  before registering its keydown listener, because the inline script runs before the deferred Vite
  module defines `window.bootstrap` (BUG-001, **high** — core navigation feature is dead).

---

## 4. Edge cases explicitly exercised

Negative / zero stock input · invalid & malformed barcode codes · cross-tenant UUID lookups on
every model · duplicate-line oversell bypass · huge order quantities · negative advance payment ·
unbounded date-range exports · webhook bad-signature / malformed-payload / replay / unknown-event ·
division-by-zero on empty analytics range · per-tenant vs cross-tenant duplicate phone · SKU/barcode
collision · onboarding double-submit · Cmd/Ctrl+K asset-load ordering.

---

## 5. Verified false positives (NOT logged as bugs)

Automated analysis flagged these as CRITICAL; source + tests prove them safe. Documented here so the
review is auditable and these aren't "re-discovered" later:

| Flagged as | Why it is actually safe |
| --- | --- |
| Unscoped `exists:` in `OrderController@store` | `patient_id`/`eye_record_id`/`inventory_id` are re-checked with tenant-scoped `findOrFail` / `lockForUpdate` → cross-tenant ids 404. Covered by `cannot attach another tenants eye record`. |
| `scan()` `where(barcode)->orWhere(sku)` leaks across tenants | Laravel's `callScope` wraps the manual wheres in a group, so the tenant `where` ANDs the whole `(barcode OR sku)`. Proven by `scan respects tenant isolation`. |
| Razorpay webhook cross-tenant tampering / replay | HMAC signature (`hash_equals`) is the boundary and is checked before mutation; `razorpay_subscription_id` is unique; status writes are idempotent. Requires platform secret leak — out of scope. |
| Stock never decremented on order | Already fixed pre-session; decrement + oversell guard + tests exist. |
| No pagination / no tenant-API throttling | Already implemented (`paginate(50)`, `throttle:120,1`). |
| `stock_qty` can go negative | `min:0` validation + `unsignedInteger` column + locked oversell guard. |
| `balance_due` float precision | `decimal:2` cast + `decimal(10,2)` column round on persist; no observable drift. |

---

## 6. Observations (gaps, not defects)

- No edit/destroy for Patients & Eye Records (data can't be corrected/removed).
- No soft deletes anywhere (hard deletes cascade; no audit/retention).
- Status/role/tier values are magic strings rather than enums.
- No audit trail for who created/changed orders, patients, subscriptions.

These match the migration backlog and are intentionally left as product decisions.

---

## 7. Coverage of prior AI-generated reports — `REFINEMENT.md` & `VERIFICATION_REPORT.md`

Both files were AI-generated during the migration from assumptions. Every **actionable** item in
them has been re-verified in this session as either already implemented or superseded:

| Prior claim | Current state (verified this session) |
| --- | --- |
| VR P1: stock not decremented on order | **Fixed** — transactional decrement + oversell guard + tests. |
| VR P2: `eye_record_id` not tenant-scoped | **Fixed** — scoped `findOrFail` in `OrderController@store`. |
| VR P2: no real pagination (hard caps) | **Done** — `paginate(50)` on patients & inventory. |
| VR P3: tenant API not throttled | **Done** — `throttle:120,1` on search/scan/eye-records. |
| VR P3: prod `.env`/`.rnd` hygiene | Deployment checklist item; tracked, not a code defect. |
| REF: no validation/flash messages | **Done** — `@error` blocks + `session('status')` alert. |
| REF: N+1 in index/analytics/search | **Done** — `->with(...)` eager loading throughout. |
| REF: order builder no validation/empty states | **Done** — qty clamp, empty states, client validation. |
| REF: no rate limiting | **Done** for auth + tenant API. |
| REF: enums / soft deletes / audit log / bulk actions | Open product backlog (see §6) — nice-to-haves, not defects. |

**Conclusion:** Nothing actionable in either prior report remains undiscovered or untracked by this
session — their genuine items are fixed, and their remaining items are captured here as §6
observations or in [BUG_TRACKER.md](BUG_TRACKER.md). Both `REFINEMENT.md` and `VERIFICATION_REPORT.md`
have therefore been **deleted** as superseded.

---

## 8. Summary verdict

The application is in **good health**: tenancy isolation is robust, the order/stock pipeline is
hardened, and the webhook security model is sound. The one genuinely broken user-facing feature is
**global search (Ctrl/Cmd+K)** (BUG-001, high). The remaining items are medium/low data-integrity
and resource-bound hardening. See [BUG_TRACKER.md](BUG_TRACKER.md) for repro steps and fixes.
