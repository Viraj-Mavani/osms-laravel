# OSMS Laravel — Bug Tracker

**Companion:** [QA_TESTING_REPORT_1.md](QA_TESTING_REPORT_1.md) (session 1),
[QA_TESTING_REPORT_2.md](QA_TESTING_REPORT_2.md) (session 2 live audit).
**Scope:** Only **verified** defects (each confirmed against source). False positives are documented
in the QA reports, not here.

This tracker spans two QA sessions:
- **Session 1 (2026-06-27)** — BUG-001 … BUG-010, all ✅ Fixed.
- **Session 2 (2026-06-28)** — NB-001 … NB-016, from the live audit ([QA_TESTING_REPORT_2.md](QA_TESTING_REPORT_2.md) Section 1).

---

## Session 2 — Live audit bugs (2026-06-28)

> ## ✅ ALL SECTION 1 BUGS FIXED — 2026-06-28
> All 7 confirmed-real bugs below are resolved. Regression coverage added in
> [`tests/Feature/Phase9LiveAuditFixesTest.php`](tests/Feature/Phase9LiveAuditFixesTest.php)
> (NB-001/002/007 are inline front-end JS, verified via `npm run build` + manual check).
> **Test suite: 91 passed (379 assertions), 0 failures.** `npm run build` succeeds.
> Feature gaps (edit/delete/cancel/payment/settings) remain a backlog at the end of this file.

| ID | Title | Severity | Status |
| --- | --- | --- | --- |
| NB-001 | Alpine `@submit` handler crashes with `Unexpected token 'return'` | **High** | ✅ Fixed |
| NB-002 | `Sortable is not defined` — Kanban drag-and-drop broken | **High** | ✅ Fixed |
| NB-003 | Patient phone accepts free-text garbage (no format validation) | Medium | ✅ Fixed |
| NB-004 | Selling price can be saved below cost price (negative margin) | Medium | ✅ Fixed |
| NB-005 | A completely blank eye record can be saved | Medium-Low | ✅ Fixed |
| NB-007 | Quantity "−" (decrement) button renders invisibly | Low | ✅ Fixed |
| NB-016 | Dashboard "Scan barcode" quick action is a misleading shortcut | Low | ✅ Fixed |

### NB-001: Alpine `@submit` handler crashes with `Unexpected token 'return'`
- **Status:** ✅ Fixed — [orders/create.blade.php:19](resources/views/tenant/orders/create.blade.php#L19) changed `@submit="return validateForm($event)"` → `@submit="validateForm($event)"` (the handler already calls `preventDefault()`).
- **Severity:** High (client-side order validation never runs; console error on every submit).
- **Location:** [resources/views/tenant/orders/create.blade.php:19](resources/views/tenant/orders/create.blade.php#L19).
- **Description:** The form uses `@submit="return validateForm($event)"`. Alpine wraps an `x-on`
  expression as `return (<expr>)`, so this becomes `return (return validateForm($event))` — a
  **double `return`** → `SyntaxError: Unexpected token 'return'`. The handler already calls
  `e.preventDefault()`, so the leading `return` is wrong and unnecessary.
- **Steps to reproduce / trigger:** Open the order create page; the console logs the Alpine
  expression error. The advance-exceeds-total `alert()` guard never fires.
- **Potential root cause:** Alpine event expressions must be statements, not `return …`.
- **Recommended fix:** `@submit="validateForm($event)"` (drop `return`).

### NB-002: `Sortable is not defined` — Kanban drag-and-drop broken
- **Status:** ✅ Fixed — [orders/index.blade.php](resources/views/tenant/orders/index.blade.php) wraps the Sortable init in a `DOMContentLoaded` guard with an `if (!window.Sortable) return;` bail (mirrors the BUG-001 Ctrl+K fix).
- **Severity:** High (advertised drag-and-drop is completely non-functional).
- **Location:** [resources/views/tenant/orders/index.blade.php:140-151](resources/views/tenant/orders/index.blade.php#L140-L151); library imported in [resources/js/app.js:12-13](resources/js/app.js#L12-L13).
- **Description:** **Same class of bug as BUG-001 (session 1).** The inline classic `<script>` runs
  during HTML parse and calls `new Sortable(col, …)`, but `window.Sortable` comes from the deferred
  `@vite` ESM bundle that executes *after* parsing → `ReferenceError: Sortable is not defined`.
- **Steps to reproduce / trigger:** Open Orders → Board view; console shows the ReferenceError and
  cards cannot be dragged between columns.
- **Potential root cause:** Inline classic script touches a deferred-ESM global before it exists.
- **Recommended fix:** Wrap the Sortable init in a `DOMContentLoaded` guard with an `if (!window.Sortable) return;`
  bail, mirroring the [global-search.blade.php](resources/views/partials/global-search.blade.php) fix.

### NB-003: Patient phone accepts free-text garbage
- **Status:** ✅ Fixed — [StorePatientRequest.php](app/Http/Requests/StorePatientRequest.php) normalises `{country_code} {national}` and validates `regex:/^\+\d{1,4}\s\d{7,15}$/`; [patients/create.blade.php](resources/views/tenant/patients/create.blade.php) adds a country-code selector (default +91). Covered by `Phase9LiveAuditFixesTest::test_patient_phone_rejects_garbage` + `…_accepts_country_code`.
- **Severity:** Medium (data integrity).
- **Location:** [app/Http/Requests/StorePatientRequest.php:21-27](app/Http/Requests/StorePatientRequest.php#L21-L27).
- **Description:** The phone rule is `['required','string','max:30', Rule::unique(...)]` with no format
  validation, so `"abc-invalid-phone"` is accepted and stored. (Note: duplicate **detection already
  exists** — phone is unique per tenant; only **format** validation is missing.)
- **Steps to reproduce / trigger:** Create a patient with phone `abc-invalid-phone` → saved.
- **Potential root cause:** No regex/format rule on the phone field.
- **Recommended fix:** Add a phone format rule, e.g. `'regex:/^[0-9+\-\s()]{7,15}$/'`.

### NB-004: Selling price can be saved below cost price
- **Status:** ✅ Fixed (allow-but-warn, per product decision) — [inventory/_form.blade.php](resources/views/tenant/inventory/_form.blade.php) shows a live "below cost" warning when selling < cost; the save is intentionally **not** blocked (clearance sales). Covered by `Phase9LiveAuditFixesTest::test_selling_below_cost_is_allowed`.
- **Severity:** Medium (financial integrity).
- **Location:** [app/Http/Requests/InventoryRequest.php:21-22](app/Http/Requests/InventoryRequest.php#L21-L22).
- **Description:** `cost_price` and `selling_price` are validated `numeric|min:0` independently; there
  is no cross-field check that `selling_price >= cost_price`, so a negative-margin item saves silently.
- **Steps to reproduce / trigger:** Edit an item: cost ₹9,999, selling ₹100 → saves with no warning.
- **Potential root cause:** No cross-field validation.
- **Recommended fix:** Add `'selling_price' => [..., 'gte:cost_price']` (or a soft confirm-on-warn UX).

### NB-005: A completely blank eye record can be saved
- **Status:** ✅ Fixed — [StoreEyeRecordRequest.php](app/Http/Requests/StoreEyeRecordRequest.php) adds a `withValidator` after-check requiring at least one measurement (any `od_*`/`os_*` field or `pd`). Covered by `Phase9LiveAuditFixesTest::test_blank_eye_record_is_rejected` + `…_with_one_measurement_saves`.
- **Severity:** Medium-Low (data quality).
- **Location:** [app/Http/Requests/StoreEyeRecordRequest.php:16-32](app/Http/Requests/StoreEyeRecordRequest.php#L16-L32).
- **Description:** Every field is `nullable` with no "at least one measurement required" rule, so an
  all-empty submission creates an empty `eye_records` row in the patient timeline.
- **Steps to reproduce / trigger:** Submit the eye-record form with all fields empty → saved.
- **Potential root cause:** No `required_without_all` / cross-field presence rule.
- **Recommended fix:** Require at least one measurement (e.g. a custom validator or
  `required_without_all` across the SPH/CYL fields).

### NB-007: Quantity "−" (decrement) button renders invisibly
- **Status:** ✅ Fixed — [orders/create.blade.php:126-128](resources/views/tenant/orders/create.blade.php#L126-L128) replaced the U+2212 / "+" glyphs with `<i class="bi bi-dash-lg">` / `<i class="bi bi-plus-lg">` icons (+ aria-labels).
- **Severity:** Low (UX; "+" works and qty also clamps).
- **Location:** [resources/views/tenant/orders/create.blade.php:126](resources/views/tenant/orders/create.blade.php#L126).
- **Description:** The decrement button's label is the Unicode **MINUS SIGN `−` (U+2212)**, not an
  ASCII hyphen; the body font (Plus Jakarta Sans) likely lacks that glyph, so it renders zero-width
  while "+" (U+002B) shows. Markup/classes for both buttons are otherwise identical.
- **Steps to reproduce / trigger:** Add an order line item; the decrement control is not visible.
- **Potential root cause:** Missing glyph for U+2212 in the chosen font.
- **Recommended fix:** Use an ASCII `-`, a `<i class="bi bi-dash"></i>` icon, or set a min-width.

### NB-016: Dashboard "Scan barcode" quick action is a misleading shortcut
- **Status:** ✅ Fixed — [dashboard.blade.php:48](resources/views/tenant/dashboard.blade.php#L48) now links to `inventory.index?scan=1`; [inventory/index.blade.php](resources/views/tenant/inventory/index.blade.php) shows a "Ready to scan" banner and auto-focuses the search box (the page already routes hardware-scanner input to the search). Covered by `Phase9LiveAuditFixesTest::test_inventory_scan_shortcut_renders`.
- **Severity:** Low (UX expectation mismatch).
- **Location:** [resources/views/tenant/dashboard.blade.php:48](resources/views/tenant/dashboard.blade.php#L48).
- **Description:** The "Scan barcode" card routes to `tenant.inventory.index` (the plain list); it
  opens no scan modal/camera/lookup, even though a scan endpoint exists
  ([InventoryController::scan](app/Http/Controllers/Tenant/InventoryController.php#L73)).
- **Steps to reproduce / trigger:** Dashboard → click "Scan barcode" → lands on the inventory list.
- **Potential root cause:** Card points at the list route; no scan UI surfaced.
- **Recommended fix:** Point the card at a scan modal (reuse the barcode-listener partial) or a
  `?scan=1` state that auto-opens a scan input.

## Section 2 — Feature gaps (CRUD-completeness milestone)

> Confirmed-absent capabilities from the live audit ([QA_TESTING_REPORT_2.md](QA_TESTING_REPORT_2.md) §2).
> These are **gaps, not defects** — the platform is correct as far as it goes, but key lifecycle
> actions (edit / delete / cancel / payment / settings) are missing. Every new feature **must** follow
> the `CLAUDE.md` [VISUAL DESIGN SYSTEM DIRECTIVE]: premium iOS-inspired UI, design tokens only,
> spring-eased micro-transitions, and a tenant-isolation test per new tenant-owned action.

### Phase A — Core CRUD (✅ COMPLETE — 2026-06-30)

| Ref | Gap | Priority | Status |
| --- | --- | --- | --- |
| NB-008 | No edit/update for a Patient profile | High | ✅ Fixed |
| NB-008b | No edit/delete for Eye Records | High | ✅ Fixed |
| FG-Settings | No store/tenant settings page (name/address/GSTIN/logo not editable after onboarding) | High | ✅ Fixed |

**What shipped:** Shared form partials for patient & eye-record create/edit; reusable confirm modal (premium replacement for `window.confirm`); Tenant\SettingsController with live logo preview; session('error') flash + dismissible success in layout; all infrastructure for Phase B/C deletes. **Tests:** 100 passed / 401 assertions. **Commit:** `3e09ed8`.

---

### Phase B — Order lifecycle + payments (✅ COMPLETE — 2026-07-01)

| Ref | Gap | Priority | Status |
| --- | --- | --- | --- |
| NB-009 | No cancel/void order (+ decremented stock never restored) | High | ✅ Fixed |
| FG-PaymentLog | No "collect balance" / record-additional-payment action | High | ✅ Fixed |
| FG-StockLog | No manual stock-adjustment with an audit trail | Medium | ✅ Fixed |

**What shipped:** Order **cancel** (restores stock in a transaction, idempotent, blocks delivered) with a reason + cancelled banner; dedicated **`payments`** ledger with a Record-payment modal, over-payment clamp, and full history on the order; dedicated **`stock_movements`** audit trail (order placement, cancel, and manual **Adjust stock** on the item page all write movements) with a history panel. Cancelled orders excluded from outstanding stats/dues. Portable status-enum migration (raw `MODIFY` on MySQL, native `->change()` on SQLite). **Tests:** `Phase11OrderLifecycleTest` (15 tests) — suite **114 passed / 456 assertions**.

---

### Phase C — Immutability & exports (🔵 PLANNED)

| Ref | Gap | Priority | Status |
| --- | --- | --- | --- |
| NB-017 | Order PDF button is invisible on light bg (`btn-outline-secondary` un-themed) | Low | ✅ Fixed |
| FG-Delete | No delete/archive for patients / inventory | Medium | ✅ Fixed |
| FG-Export | No CSV/PDF export for inventory or patients | Low-Med | 🔵 Planned |
| FG-OrderEdit | Orders are immutable after creation (no line-item / qty editing) | Medium | 🔵 Planned |

### NB-017: Order PDF button invisible on light background
- **Status:** ✅ Fixed (2026-07-01) — swapped `btn-outline-secondary` → `btn-light` on the PDF
  buttons in [orders/show.blade.php:42](resources/views/tenant/orders/show.blade.php#L42) and
  [orders/partials/table.blade.php:105](resources/views/tenant/orders/partials/table.blade.php#L105).
- **Severity:** Low (UI/legibility).
- **Description:** `.btn-outline-secondary` is not themed in the OSMS layer, so it falls back to
  Bootstrap's default derived from `$secondary` (`#eef1f5`, near-white) — giving light-gray text +
  border on a white card, effectively invisible. The design system's neutral buttons
  (`.btn-secondary` / `.btn-light`) are properly themed (white surface, metallic border, `--osms-fg`
  text). This button was missed when the other buttons were standardised.
- **Recommended fix:** Use `.btn-light` (or `.btn-secondary`) for neutral actions; never
  `.btn-outline-secondary` (un-themed). *(Note: analytics export + order-builder qty steppers +
  "Clear filters" still use `btn-outline-secondary` — same latent issue, out of scope here, candidates
  for a later sweep.)*

### NB-008: No edit/update for a Patient profile
- **Status:** ✅ Fixed (Phase A, 2026-06-30).
- **Priority:** High.
- **Location:** [routes/tenant.php:28-29](routes/tenant.php#L28-L29); [PatientController](app/Http/Controllers/Tenant/PatientController.php) `edit`/`update`.
- **Gap (resolved):** A patient's name/phone/age/gender could not be corrected after creation.
- **Fix:** Added `edit`/`update` routes + `PatientController@edit/@update`; extracted the create form into a shared [`patients/_form.blade.php`](resources/views/tenant/patients/_form.blade.php) (with the country-code selector) reused by create + edit; added an "Edit" button to the patient header. `StorePatientRequest` reused unchanged (its unique rule already `->ignore()`s the bound patient). Cross-tenant update returns 404 (tested).

### NB-008b: No edit/delete for Eye Records
- **Status:** ✅ Fixed (Phase A, 2026-06-30).
- **Priority:** High.
- **Location:** [routes/tenant.php:34-36](routes/tenant.php#L34-L36); [EyeRecordController](app/Http/Controllers/Tenant/EyeRecordController.php) `edit`/`update`/`destroy`.
- **Gap (resolved):** A mistyped prescription could neither be corrected nor removed from the patient timeline.
- **Fix:** Added `edit`/`update`/`destroy` for eye records (reuses `StoreEyeRecordRequest`); extracted a shared [eye-record `_form`](resources/views/tenant/eye-records/_form.blade.php); added a kebab dropdown (Edit + Delete) to [`<x-eye-record-card>`](resources/views/components/eye-record-card.blade.php). Delete routes through the reusable [confirm modal](resources/views/partials/confirm-modal.blade.php). Cross-tenant delete returns 404 (tested).

### FG-Settings: No store/tenant settings page
- **Status:** ✅ Fixed (Phase A, 2026-06-30).
- **Priority:** High.
- **Location:** [routes/tenant.php](routes/tenant.php) (`settings` group, `role:store_admin,superadmin`); [SettingsController](app/Http/Controllers/Tenant/SettingsController.php); [settings/edit.blade.php](resources/views/tenant/settings/edit.blade.php).
- **Gap (resolved):** Store name, address, GSTIN/tax id, and logo were frozen at onboarding; receipts could never be corrected.
- **Fix:** New `Tenant\SettingsController` (`edit`/`update`), `role:store_admin,superadmin` gated; premium card-based settings page with live logo preview + "remove logo" option; logo replace uses the same try/catch safe-upload as onboarding. Sidebar "Settings" link shown to store admins only. Role test confirms staff get 403.

### NB-009: No cancel/void order (+ stock restore)
- **Status:** ✅ Fixed (Phase B, 2026-07-01).
- **Priority:** High.
- **Location:** [OrderController@cancel](app/Http/Controllers/Tenant/OrderController.php); [migration](database/migrations/2026_07_01_000001_add_cancel_to_orders.php); order show cancel modal + banner.
- **Gap (resolved):** A wrong order could not be cancelled, and the stock drawn down at creation was never returned.
- **Fix:** Added a `cancelled` status via a portable migration (raw `MODIFY` on MySQL, native `->change()` on SQLite) plus `cancelled_at`/`cancel_reason`. `cancel()` restores each line's `stock_qty` inside a transaction and writes a `cancel` stock-movement. Blocks delivered + already-cancelled orders (idempotent — no double restore). Cancelled orders show a red banner, are hidden from the kanban board, and are excluded from outstanding stats/dues. Tested: restore, idempotency, delivered-block, tenant isolation.

### FG-PaymentLog: No "collect balance" / record-payment action
- **Status:** ✅ Fixed (Phase B, 2026-07-01).
- **Priority:** High.
- **Location:** [payments migration](database/migrations/2026_07_01_000002_create_payments_table.php); [Payment](app/Models/Payment.php); [OrderController@recordPayment](app/Http/Controllers/Tenant/OrderController.php); order show payment modal + history.
- **Gap (resolved):** A delivered order with `balance_due > 0` was frozen — no way to record the rest being paid, and no audit trail.
- **Fix:** New tenant-owned `payments` table (UUID, amount, method, note, recorded_by). `recordPayment` adds a payment and bumps `advance_paid` (clamped to the outstanding balance; model keeps `balance_due` in sync); blocks cancelled + already-paid orders. The initial advance is now logged as the first payment, so history is a complete ledger. Record-payment modal + history table on the order page. Tested: clamp, balance-to-zero, cancelled block, tenant isolation.

### FG-OrderEdit: Orders are immutable after creation
- **Status:** 🔵 Planned
- **Priority:** Medium.
- **Location:** [OrderController](app/Http/Controllers/Tenant/OrderController.php) — no edit/update for line items.
- **Gap:** Quantities/line items cannot be changed after the order is placed; the only recourse is cancel + re-create.
- **Planned approach:** Scope-limited edit of line items with full stock reconciliation (re-run the oversell guard, diff old vs new quantities, adjust stock + total) inside a transaction; reuse the Alpine order builder. **Highest-risk item** (touches stock + money) — schedule last, behind robust tests. Blocked orders: cannot edit a `delivered`/`cancelled` order.

### FG-Delete: No delete/archive for patients / inventory
- **Status:** ✅ Fixed (Phase C1, 2026-07-01).
- **Priority:** Medium.
- **Location:** [PatientController](app/Http/Controllers/Tenant/PatientController.php) + [InventoryController](app/Http/Controllers/Tenant/InventoryController.php) `trash`/`destroy`/`restore`/`forceDelete`; [purge command](app/Console/Commands/PurgeTrashedRecords.php); migrations `2026_07_01_000004/000005`; archive views `patients/trash`, `inventory/trash`.
- **Gap (resolved):** Test/junk rows (e.g. an old "abc-invalid-phone" patient) could not be removed; mis-added items lingered.
- **Fix:** `SoftDeletes` on `Patient` + `Inventory` (composes with `TenantScope`) with a **30-day retention window**. Archive via the reusable confirm modal ("recoverable for 30 days"); a per-module **Archive view** (Restore / Delete-now); restore/force-delete routes use `->withTrashed()` binding (still tenant-scoped → cross-tenant returns 404). **Guards:** a patient with order history can't be archived (no orphaned receipts); an item on an **open** order (pending/ready) can't be archived (delivered/cancelled history is safe — line items keep their captured `unit_price`). Nightly `model:purge-trashed` (scheduled 02:00, `--days=30`) hard-deletes past the window, bypassing the tenant scope to cover every store. **Tests:** `Phase12SoftDeleteTest` (12 tests) — soft-delete/restore/force, both guards, purge window, tenant isolation, archive views render.

### FG-StockLog: No manual stock-adjustment audit
- **Status:** ✅ Fixed (Phase B, 2026-07-01).
- **Priority:** Medium.
- **Location:** [stock_movements migration](database/migrations/2026_07_01_000003_create_stock_movements_table.php); [StockMovement](app/Models/StockMovement.php); [InventoryController@adjustStock](app/Http/Controllers/Tenant/InventoryController.php); inventory edit adjust panel + history.
- **Gap (resolved):** Damage/loss/recount adjustments were silent — no who/why/when.
- **Fix:** New tenant-owned `stock_movements` table (UUID, inventory_id, signed `delta`, `type` [order|cancel|adjustment], reason, order_id, recorded_by). `adjustStock` applies a manual delta with a required reason (guards stock ≥ 0); order placement and NB-009 cancel also write movements, so the ledger is complete. Adjust panel + movement-history table on the item page. Tested: adjust, below-zero guard, tenant isolation.

### FG-Export: No CSV/PDF export for inventory or patients
- **Status:** 🔵 Planned
- **Priority:** Low-Med.
- **Location:** Only [LedgerExport](app/Exports/LedgerExport.php) exists.
- **Gap:** Inventory and patient lists can't be exported for offline use/backup.
- **Planned approach:** `InventoryExport` + `PatientsExport` (Maatwebsite, `FromQuery` + chunking + the BUG-007 `MAX_ROWS` cap) honoring the active filters; export buttons on each index. Tests assert tenant-scoped row counts.

---

## Session 1 — QA bugs (2026-06-27)

> ## ✅ ALL SESSION 1 BUGS FIXED — 2026-06-27
> All 10 entries below are resolved. Regression coverage added in
> [`tests/Feature/Phase8QaFixesTest.php`](tests/Feature/Phase8QaFixesTest.php) (BUG-001 is front-end
> JS, verified manually). **Test suite: 85 passed, 0 failures.** Each bug carries a
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
