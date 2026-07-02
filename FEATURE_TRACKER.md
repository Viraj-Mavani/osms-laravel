# OSMS Laravel — Feature Tracker

**Companion:** [QA_TESTING_REPORT_3.md](QA_TESTING_REPORT_3.md) (Session 3 impact analysis) ·
[BUG_TRACKER.md](BUG_TRACKER.md) (defects + earlier feature gaps).
**Scope:** planned **feature work** (net-new capabilities), its build order, and status. Deep
per-feature impact analysis and the butterfly maps live in
[QA_TESTING_REPORT_3.md](QA_TESTING_REPORT_3.md); this file is the **build tracker** — what we're
building, in what order, and where each stands.

> Every feature must follow the `CLAUDE.md` [VISUAL DESIGN SYSTEM DIRECTIVE] (premium iOS-inspired UI,
> design tokens only, spring-eased motion) and ship with a `PhaseNN…Test` suite carrying a
> tenant-isolation assertion for every new tenant-owned action.

---

## Locked decisions (Session 3)

Settled with the product owner on 2026-07-01 (see [QA_TESTING_REPORT_3.md](QA_TESTING_REPORT_3.md)
"Open decisions"):

- **D1 — Customer model:** **unify** into one `Customer` entity ("patient" = a customer who has a
  prescription). **Plus** inline **auto-register on order creation** — no separate registration step
  (find-or-create by `(tenant_id, phone)`).
- **D2 — Walk-ins:** name + phone **stay required** (`customer_id`/`phone` remain NOT NULL); speed
  comes from inline create, not nullable fields.
- **D6 — Permissions:** **all staff** may apply discounts / custom prices.
- **D7 — Analytics:** revenue = **net of discount**, with the order-level discount **allocated
  pro-rata to lines** so brand revenue reconciles.
- **Defaults (confirm to change):** D3 one "Customers" section + a "Patients" filter · D4 rename
  `patients.*` → `customers.*` now · D5 order-level discount + per-line custom price · D8 snapshot
  per-line `list_price` · D9 below-cost allow-but-warn · D10 `selling_price` = list/MRP · D11 barcode
  Print + Download PNG named by SKU.

---

## Build order

Barcode ships first (independent, zero shared surface). Customers is the foundational rewire, then the
order money-model. Pricing semantics (Task 3.1) is folded into the order money-model work.

| # | Ref | Feature | Depends on | Priority | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | FT-Barcode | Printable / downloadable barcode label on Edit item (Task 3.2) | — | Low | ✅ Done |
| 2 | FT-Customers | Unified customer entity + inline auto-register in orders (Task 1) | — | High | 🔵 Planned |
| 3 | FT-OrderMoney | Order discount + custom price + advance payment method + builder redesign (Task 2, incl. 3.1 pricing semantics) | FT-Customers | High | 🔵 Planned |

---

## FT-Barcode — Printable / downloadable barcode label (Task 3.2)

- **Status:** ✅ Done (2026-07-01).
- **Priority:** Low (additive, independent — touches nothing FT-Customers / FT-OrderMoney touch).
- **Scope:** On **Edit item**, a "Barcode label" panel renders the item's `barcode` as a **Code128**
  symbol (client-side via the already-bundled `JsBarcode`) with the **SKU** as the human-readable line.
  **Download** saves a PNG named `{SKU}.png`; **Print** opens a print-friendly label window (title =
  SKU, so print-to-PDF also defaults to the SKU).
- **Why client-side:** no new dependency, fully offline, and the rendered Code128 of the `barcode`
  value round-trips through [`InventoryController::scan`](app/Http/Controllers/Tenant/InventoryController.php)
  (which matches `barcode` or `sku`).
- **Location:** [inventory/edit.blade.php](resources/views/tenant/inventory/edit.blade.php) (panel +
  guarded inline script); relies on [`JsBarcode`](resources/js/app.js).
- **Edge cases handled:** SKU sanitised for a safe filename; label shows brand/model above the code;
  script is `DOMContentLoaded`-guarded with an `if (!window.JsBarcode) return;` bail (mirrors the
  BUG-001 / NB-002 deferred-ESM pattern).
- **Tests:** front-end render is manual (canvas/SVG); `Phase15BarcodeLabelTest` (3) asserts the edit
  page exposes the barcode panel + Download/Print controls and the SKU/barcode data, the label↔scan
  round-trip resolves, and the page is tenant-isolated. Suite: **152 passed / 601 assertions**.
- **Commit:** _(this change)_.

---

## FT-Customers — Unified customer entity + inline auto-register (Task 1)

- **Status:** 🔵 Planned (foundational — highest churn, lowest math risk).
- **Priority:** High.
- **Scope:** Rename `patients` → `customers`; "patient" becomes a derived role (has ≥ 1 eye record).
  `orders.patient_id` → `customer_id`; `eye_records.patient_id` → `customer_id`. Order builder gains a
  combined "search existing **or** type name + phone" control that **find-or-creates** the customer on
  submit — no separate registration step. One "Customers" section with a "Patients" filter.
- **Impact analysis + full butterfly list:** [QA_TESTING_REPORT_3.md](QA_TESTING_REPORT_3.md) → Task 1.
- **Key risks:** data-preserving migration (orders keep their contact); route renames
  (`patients.*` → `customers.*`) are silent-break risk → grep-sweep + smoke test; large but mechanical
  test/fixture churn.
- **Tests:** `PhaseNNCustomersTest` — CRUD + tenant isolation, inline auto-register (find-or-create),
  promote-to-patient via eye record, order for a plain customer, backfill correctness, global search.

---

## FT-OrderMoney — Discount + custom price + advance method + redesign (Task 2 + 3.1)

- **Status:** 🔵 Planned (money-model change — build after FT-Customers).
- **Priority:** High.
- **Scope:**
  - **Discount** (percent or amount) with live calc: add `subtotal`, `discount_type`, `discount_value`,
    `discount_amount` to `orders`; `total_amount = subtotal − discount_amount`.
  - **Custom selling price** per line (already stored in `order_items.unit_price`); snapshot
    `list_price` per line for discount reporting. Below-cost allow-but-warn (NB-004).
  - **Advance payment method** captured on the initial `Payment` (drops the hardcoded cash); surfaced
    on the receipt + reports.
  - **Create/Edit order redesign** to absorb the customer picker + these controls without clutter.
  - **Pricing semantics (Task 3.1):** `selling_price` = default/list (MRP); revenue always reads the
    order line price.
- **Impact analysis + full butterfly list:** [QA_TESTING_REPORT_3.md](QA_TESTING_REPORT_3.md) → Task 2
  & Task 3.1. **Highest-care area:** analytics revenue = net + **pro-rata discount allocation** so
  brand revenue reconciles (D7).
- **Interactions:** must re-reconcile with the FG-OrderEdit flow (C3) — discount + custom prices
  preserved on edit; rounding defined consistently (UI / server / PDF / analytics).
- **Tests:** `PhaseNNOrderMoneyTest` — discount clamps (percent/amount), custom price (below-cost
  warn), advance method persisted, receipt renders discount + method, analytics net revenue +
  brand reconciliation, backward-compat (no discount → old totals), edit interaction.

---

*Update this tracker as each feature moves Planned → In progress → Done, mirroring the BUG_TRACKER
convention (status line + commit ref + test summary).*
