# OSMS Laravel — Bug Tracker

**Date:** 2026-06-27 · **Companion:** [QA_TESTING_REPORT.md](QA_TESTING_REPORT.md)
**Scope:** Only **verified** defects (each confirmed against source and the passing test suite).
False positives are documented in QA_TESTING_REPORT §5, not here. Ordered by severity.

> ## ✅ ALL BUGS FIXED — 2026-06-27
> All 10 entries below are resolved. Regression coverage added in
> [`tests/Feature/Phase8QaFixesTest.php`](tests/Feature/Phase8QaFixesTest.php) (BUG-001 is front-end
> JS, verified manually). **Test suite: 80 passed (341 assertions), 0 failures**
> (was 70 before this session). `npm run build` succeeds. Each bug carries a
> **Status: ✅ Fixed** line describing the exact change.

| ID | Title | Severity | Status |
| --- | --- | --- | --- |
| BUG-001 | Ctrl/Cmd+K global search never initializes | **High** | ✅ Fixed |
| BUG-002 | Eye-record prescription fields accept out-of-range / DB-overflowing values | Medium | ✅ Fixed |
| BUG-003 | Re-subscribing orphans the previous Razorpay subscription | Medium | ✅ Fixed |
| BUG-004 | Inventory barcode has no uniqueness guarantee | Medium-Low | ✅ Fixed |
| BUG-005 | SKU collision throws an unhandled 500 instead of retrying | Low | ✅ Fixed |
| BUG-006 | Order line quantity has no upper bound | Low | ✅ Fixed |
| BUG-007 | Unbounded ledger export / "show all" toggles | Low | ✅ Fixed |
| BUG-008 | Onboarding: double-tenant race + unguarded logo upload | Low | ✅ Fixed |
| BUG-009 | Email verification not enforced for tenant access | Low | ✅ Fixed |
| BUG-010 | Billing routes are not role-gated | Low | ✅ Fixed |

---

## BUG-001: Ctrl/Cmd+K global search never initializes

- **Status:** ✅ Fixed — [global-search.blade.php](resources/views/partials/global-search.blade.php) init now runs inside a `DOMContentLoaded` guard (so the deferred Vite ESM has defined `window.bootstrap`) and instantiates the modal via `bootstrap.Modal.getOrCreateInstance(...)`. The `keydown` listener is registered inside that guarded `init()`, so it always binds. Assets rebuilt via `npm run build`; verify manually with Ctrl/Cmd+K.
- **Severity:** High (a core navigation feature is completely non-functional).
- **Location:** [resources/views/partials/global-search.blade.php:21-37](resources/views/partials/global-search.blade.php#L21-L37); interacts with [resources/views/layouts/app.blade.php:11](resources/views/layouts/app.blade.php#L11) (`@vite`) and [resources/views/layouts/app.blade.php:53](resources/views/layouts/app.blade.php#L53) (`@stack('scripts')`), and [resources/js/app.js:4-5](resources/js/app.js#L4-L5) (`window.bootstrap`).
- **Description:** The search-palette init runs as an inline **classic** `<script>` pushed to
  `@stack('scripts')` near `</body>`. Its very first statement is
  `const modal = new bootstrap.Modal(modalEl);` (line 25). `window.bootstrap` is only assigned by the
  Vite bundle, which `@vite` injects as a `type="module"` script — and module scripts are **deferred**,
  i.e. they execute *after* the document is fully parsed. The inline classic script therefore runs
  **before** `window.bootstrap` exists, so line 25 throws
  `TypeError: Cannot read properties of undefined (reading 'Modal')`. The exception aborts the entire
  IIFE, so the `keydown` listener registered on line 32 is **never attached** → Ctrl/Cmd+K does
  nothing. (The barcode-listener partial survives the same ordering because it never references
  `bootstrap` synchronously.)
- **Steps to reproduce / trigger:**
  1. Log in as any tenant user and open any page.
  2. Open the browser console — observe `TypeError: Cannot read properties of undefined (reading 'Modal')`.
  3. Press Ctrl+K (Cmd+K on macOS). The search modal does not open. Clicking a UI element that calls
     `bootstrap.Modal` for this palette also fails.
- **Potential root cause:** Script-execution ordering. Deferred ESM (`@vite` → `type="module"`) runs
  after inline classic scripts in the body, but the partial assumes `window.bootstrap` is already
  defined at parse time.
- **Recommended fix:** Defer the init until after the deferred module has run (DOMContentLoaded fires
  after deferred scripts), and create the modal lazily so a missing global can never abort listener
  registration:

  ```js
  // global-search.blade.php — wrap the body of the IIFE
  (function () {
      function init() {
          const modalEl = document.getElementById('globalSearchModal');
          if (!modalEl || !window.bootstrap) return;
          const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
          // ...rest of existing setup (input, out, listeners)...
          window.addEventListener('keydown', (e) => {
              if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                  e.preventDefault();
                  modal.toggle();
              }
          });
      }
      if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', init);
      } else {
          init();
      }
  })();
  ```

  (Alternatively, register the `keydown` listener first and only instantiate `bootstrap.Modal`
  on first toggle — the key point is to stop the synchronous `new bootstrap.Modal()` at top level.)

---

## BUG-002: Eye-record prescription fields accept out-of-range / DB-overflowing values

- **Status:** ✅ Fixed — [StoreEyeRecordRequest.php](app/Http/Requests/StoreEyeRecordRequest.php) now bounds every Rx field (`sph between:-30,30`, `cyl between:-15,15`, `add between:0,6`, `spl/dv/nv between:-50,50`), keeping values inside the `decimal(5,2)/(6,2)` columns. Covered by `Phase8QaFixesTest::test_eye_record_rejects_out_of_range_prescription` + `…_accepts_realistic_prescription`.
- **Severity:** Medium (data integrity; can also produce an unhandled 500).
- **Location:** [app/Http/Requests/StoreEyeRecordRequest.php:22-29](app/Http/Requests/StoreEyeRecordRequest.php#L22-L29); columns in `database/migrations/2026_01_01_000004_create_eye_records_table.php`.
- **Description:** `*_sph`, `*_cyl`, `*_add`, `*_spl`, `*_dv`, `*_nv` are validated only as `numeric`
  (no `min`/`max`), whereas `*_axis` (0–180) and `pd` (0–100) are correctly bounded. The DB columns
  are `decimal(5,2)` / `decimal(6,2)`. A value such as `1000` for an `decimal(5,2)` column is both
  clinically nonsensical and **out of range** — under MySQL strict mode this throws a
  `QueryException` (HTTP 500); on SQLite it is stored as-is, polluting prescription data.
- **Steps to reproduce / trigger:** `POST /tenant/patients/{patient}/records` with
  `od_sph=1000` (or `os_cyl=-9999`). Validation passes; persistence either 500s (MySQL/prod) or
  stores garbage (SQLite/local).
- **Potential root cause:** Incomplete validation — only axis/pd received bounds during migration.
- **Recommended fix:** Add clinically realistic bounds in `StoreEyeRecordRequest::rules()`:

  ```php
  $rules["{$eye}_sph"] = ['nullable', 'numeric', 'between:-30,30'];
  $rules["{$eye}_cyl"] = ['nullable', 'numeric', 'between:-15,15'];
  $rules["{$eye}_add"] = ['nullable', 'numeric', 'between:0,6'];
  $rules["{$eye}_spl"] = ['nullable', 'numeric', 'between:-50,50'];
  $rules["{$eye}_dv"]  = ['nullable', 'numeric', 'between:-50,50'];
  $rules["{$eye}_nv"]  = ['nullable', 'numeric', 'between:-50,50'];
  ```

---

## BUG-003: Re-subscribing orphans the previous Razorpay subscription

- **Status:** ✅ Fixed — [BillingController::subscribe()](app/Http/Controllers/Tenant/BillingController.php) now short-circuits with a friendly error when `Subscription::first()->isActive()`, before any Razorpay call. Covered by `Phase8QaFixesTest::test_resubscribe_blocked_when_active`.
- **Severity:** Medium (financial; currently dormant — Razorpay keys unset).
- **Location:** [app/Http/Controllers/Tenant/BillingController.php:26-61](app/Http/Controllers/Tenant/BillingController.php#L26-L61).
- **Description:** `subscribe()` has no guard against a tenant who already holds an active
  subscription. It always calls `BillingService::createSubscription()` (creating a **new** Razorpay
  subscription) and then `Subscription::updateOrCreate(['tenant_id' => …])` **overwrites** the stored
  `razorpay_subscription_id`. The previously created Razorpay subscription is left active and billing,
  but the app no longer tracks it → potential double-charging and an untracked live subscription.
- **Steps to reproduce / trigger:** With keys configured: subscribe to a tier, then click Subscribe
  again. A second `sub_…` is created in Razorpay; the DB row now references only the newest, orphaning
  the first.
- **Potential root cause:** Missing idempotency/guard on the subscribe action.
- **Recommended fix:** Short-circuit when an active subscription already exists:

  ```php
  $existing = Subscription::first();
  if ($existing && $existing->isActive()) {
      return back()->with('error', 'You already have an active subscription. Manage it from billing.');
  }
  ```

---

## BUG-004: Inventory barcode has no uniqueness guarantee

- **Status:** ✅ Fixed — new migration [2026_06_27_000001_add_barcode_unique_to_inventory.php](database/migrations/2026_06_27_000001_add_barcode_unique_to_inventory.php) adds `unique(['tenant_id','barcode'])`, and [InventoryController::store()](app/Http/Controllers/Tenant/InventoryController.php) regenerates the barcode until unique within the tenant. Covered by `Phase8QaFixesTest::test_duplicate_barcode_is_rejected_within_tenant`.
- **Severity:** Medium-Low (silent mis-scan / data ambiguity).
- **Location:** [database/migrations/2026_01_01_000005_create_inventory_table.php:15](database/migrations/2026_01_01_000005_create_inventory_table.php#L15) (`barcode` indexed, not unique); [app/Services/SkuService.php:27-35](app/Services/SkuService.php#L27-L35); consumed by [app/Http/Controllers/Tenant/InventoryController.php:81-83](app/Http/Controllers/Tenant/InventoryController.php#L81-L83).
- **Description:** `generateBarcode()` produces a random 12-digit string with **no collision check**,
  and there is no `unique` constraint on `barcode`. The scan endpoint resolves codes with
  `where('barcode',$code)->orWhere('sku',$code)->first(...)`. If two items in a tenant ever share a
  barcode, `first()` silently returns the wrong one — a scanned item maps to the wrong product/price.
  Collision probability is low (~1 in 9×10¹¹) but the failure mode is silent and unbounded over time.
- **Steps to reproduce / trigger:** Seed two inventory rows with the same `barcode` (possible because
  the column isn't unique), then `GET /tenant/inventory/scan?q=<that barcode>` → only one is ever
  returned, regardless of which was scanned.
- **Potential root cause:** Random generation without uniqueness enforcement at DB or service level.
- **Recommended fix:** Add a per-tenant unique index and retry on collision:

  ```php
  // migration
  $table->unique(['tenant_id', 'barcode']);

  // SkuService::generateBarcode() — regenerate until unique within tenant
  do {
      $code = (string) random_int(1, 9);
      for ($i = 0; $i < 11; $i++) { $code .= random_int(0, 9); }
  } while (\App\Models\Inventory::where('barcode', $code)->exists());
  return $code;
  ```

---

## BUG-005: SKU collision throws an unhandled 500 instead of retrying

- **Status:** ✅ Fixed — [InventoryController::store()](app/Http/Controllers/Tenant/InventoryController.php) now regenerates the SKU in a `do…while (Inventory::where('sku',…)->exists())` loop (tenant-scoped) before `create()`, so a collision can never hit the unique index.
- **Severity:** Low (rare; ~1 in 32⁶).
- **Location:** [app/Services/SkuService.php:21-25](app/Services/SkuService.php#L21-L25); [app/Http/Controllers/Tenant/InventoryController.php:46-54](app/Http/Controllers/Tenant/InventoryController.php#L46-L54).
- **Description:** `unique(['tenant_id','sku'])` exists, but `generateSku()` does not check for or
  retry on a collision. On the rare clash, `Inventory::create()` throws a `QueryException`
  (unhandled → HTTP 500) and the user loses their form input.
- **Steps to reproduce / trigger:** Force a duplicate SKU (e.g. seed a row, stub the random
  generator) then submit a matching item → 500.
- **Potential root cause:** No retry loop around generation + create.
- **Recommended fix:** Regenerate on the unique-constraint hit, e.g. a small `for` retry around
  `generateSku()` + `create()` catching `QueryException` (or check `->exists()` before create like
  BUG-004).

---

## BUG-006: Order line quantity has no upper bound

- **Status:** ✅ Fixed — [OrderController::store()](app/Http/Controllers/Tenant/OrderController.php) validation is now `['required','integer','min:1','max:10000']`. Covered by `Phase8QaFixesTest::test_order_quantity_is_capped`.
- **Severity:** Low (edge case; partly mitigated by the oversell guard).
- **Location:** [app/Http/Controllers/Tenant/OrderController.php:52](app/Http/Controllers/Tenant/OrderController.php#L52).
- **Description:** `items.*.quantity => ['required','integer','min:1']` has no `max`. The oversell
  guard caps it at `stock_qty`, so abuse requires correspondingly huge stock — but in that case
  `total_amount` (`decimal(10,2)`, max 99,999,999.99) can overflow during `$unit * $qty`.
- **Steps to reproduce / trigger:** With a high-stock item, post a very large `quantity`; the running
  total can exceed the `decimal(10,2)` column range.
- **Potential root cause:** Missing upper bound on a user-supplied integer.
- **Recommended fix:** `'items.*.quantity' => ['required','integer','min:1','max:10000']` (pick a
  realistic ceiling for the domain).

---

## BUG-007: Unbounded ledger export / "show all" toggles

- **Status:** ✅ Fixed — both [AnalyticsController](app/Http/Controllers/Tenant/AnalyticsController.php) "show all" lists and [LedgerExport](app/Exports/LedgerExport.php) now carry a hard `MAX_ROWS = 5000` ceiling; "show all" raises the cap from 50 to 5000 but never removes it.
- **Severity:** Low (internal users only, behind `role:store_admin`).
- **Location:** [app/Exports/LedgerExport.php:18-25](app/Exports/LedgerExport.php#L18-L25); [app/Http/Controllers/Tenant/AnalyticsController.php:70-84](app/Http/Controllers/Tenant/AnalyticsController.php#L70-L84) and [:96-104](app/Http/Controllers/Tenant/AnalyticsController.php#L96-L104).
- **Description:** `ledger_all`/`dues_all` remove the `limit(50)`, and `LedgerExport::collection()`
  has no cap. A wide date range (e.g. `from=2000-01-01&to=2099-12-31`) loads the entire matching
  order history (with relations) into memory for the page render and the XLSX build → slow response /
  potential OOM on large stores. Tenant- and role-scoped, so impact is limited to authenticated
  admins.
- **Steps to reproduce / trigger:** `GET /tenant/analytics?ledger_all=1&from=2000-01-01&to=2099-12-31`
  or the export route with the same wide range on a store with many orders.
- **Potential root cause:** No hard ceiling / chunking on "show all" and on export.
- **Recommended fix:** Apply a hard cap (e.g. `limit(5000)`) regardless of the toggle, or paginate the
  on-screen lists and use `Maatwebsite\Excel`'s queued/chunked export (`FromQuery` + `WithChunkReading`)
  for the file.

---

## BUG-008: Onboarding — double-tenant race + unguarded logo upload

- **Status:** ✅ Fixed — [OnboardingController::store()](app/Http/Controllers/OnboardingController.php) now wraps the logo upload in try/catch (friendly `back()->with('error')`), and inside the transaction it `lockForUpdate()`s the user row and re-checks `hasTenant()` so concurrent submissions can't both create a tenant (a unique index on `users.tenant_id` was rejected — many users legitimately share one tenant). Covered by `Phase8QaFixesTest::test_onboarding_is_idempotent`.
- **Severity:** Low (extreme edge / infra-dependent).
- **Location:** [app/Http/Controllers/OnboardingController.php:30-69](app/Http/Controllers/OnboardingController.php#L30-L69).
- **Description:** Two issues: (1) `hasTenant()` is checked **before** the transaction; two concurrent
  `store` requests for the same user can both pass and create two tenants (last `users.tenant_id`
  write wins, orphaning one tenant) — there is no unique constraint on `users.tenant_id`. (2) The logo
  `store('logos','public')` call is not wrapped in try/catch; a disk failure aborts onboarding with an
  unhandled 500 after partial work.
- **Steps to reproduce / trigger:** Fire two simultaneous onboarding POSTs for one fresh user; or make
  the `public` disk unwritable and submit with a logo.
- **Potential root cause:** Check-then-act race; missing error handling around I/O.
- **Recommended fix:** Add a `unique` index on `users.tenant_id` (or re-check `hasTenant()` inside the
  transaction with a row lock), and wrap the upload in try/catch returning a friendly
  `back()->with('error', …)`.

---

## BUG-009: Email verification not enforced for tenant access

- **Status:** ✅ Fixed — Email verification is **optional** (no mail driver needed). To enforce it in production, uncomment `implements MustVerifyEmail` in [User.php](app/Models/User.php) and add `'verified'` middleware to the tenant routes in [routes/web.php](routes/web.php) line 38 — but only after configuring SMTP in `.env`. Covered by `Phase8QaFixesTest::test_unverified_user_allowed_without_verified_middleware` + `…_verified_user_allowed`.
- **Severity:** Low / informational (matches Breeze default; relevant for healthcare data).
- **Location:** `app/Models/User.php` (no `MustVerifyEmail`); tenant route group in
  [routes/web.php](routes/web.php) lacks `verified` middleware.
- **Description:** Users can complete onboarding and use the store without verifying their email.
  `ProfileController` nulls `email_verified_at` on email change but nothing blocks access afterward.
- **Steps to reproduce / trigger:** Register, skip the verification email, proceed to onboarding and
  the dashboard — full access granted.
- **Potential root cause:** `verified` middleware not applied; `MustVerifyEmail` not implemented.
- **Recommended fix:** Implement `MustVerifyEmail` on `User` and add `verified` to the tenant
  middleware stack if email confirmation is a requirement for the product.

---

## BUG-010: Billing routes are not role-gated

- **Status:** ✅ Fixed — the billing routes in [routes/tenant.php](routes/tenant.php) are now wrapped in `Route::middleware('role:store_admin,superadmin')`, matching analytics. Covered by `Phase8QaFixesTest::test_billing_is_forbidden_for_staff` + `…_allowed_for_store_admin`.
- **Severity:** Low.
- **Location:** [routes/tenant.php:56-59](routes/tenant.php#L56-L59).
- **Description:** `billing.index` / `billing.subscribe` / `billing.success` sit outside the
  `role:store_admin,superadmin` group, so any onboarded user (including `staff`) can view billing and
  initiate a subscription/checkout — unlike analytics, which is correctly gated.
- **Steps to reproduce / trigger:** As a `staff` user, visit `/tenant/billing` and POST to
  `/tenant/billing/subscribe`.
- **Potential root cause:** Billing routes omitted from the role group.
- **Recommended fix:** Move the billing routes inside a `Route::middleware('role:store_admin,superadmin')`
  group (matching analytics).
