# OSMS Laravel — Live Audit Analysis (Report 2)

**Source:** Autonomous Web-Navigation QA agent (Claude for Chrome) live run against
`https://osms.satvscript.com/tenant`, 28 Jun 2026.
**This document:** Senior-QA verification of every item in that report against the actual source,
with confirmed root causes.
**Companion:** [BUG_TRACKER.md](BUG_TRACKER.md), [QA_TESTING_REPORT_1.md](QA_TESTING_REPORT_1.md) (previous session).

> The live site's console references `app-NXGmmfmY.js` — the current build hash — so every finding
> below was checked against the code that is actually deployed.

---

## Verdict & triage summary

The agent raised **9 bugs + 6 UX issues + 24 feature gaps**. After source verification:

| Outcome | Count | Items |
| --- | --- | --- |
| ✅ Confirmed real bug | 7 | NB-001, NB-002, NB-003, NB-004, NB-005, NB-007, NB-016 |
| ✅ Confirmed real feature gap | 9 | NB-008, NB-009, FG-Settings, FG-Delete, FG-OrderEdit, FG-StockLog, FG-Export, FG-PaymentLog, FG-DOB |
| 🟡 Real but UX-enhancement / by-design | 5 | NB-006, UX-002, UX-003, UX-004, UX-006 |
| ❌ False positive (already correct) | 3 | UX-001, UX-005, "no duplicate phone detection" |
| ⚠️ Partly wrong | 1 | FG-Pagination (patients & inventory already paginated) |

Two of the "critical" bugs (NB-001, NB-002) are **genuine JS runtime errors** and should be fixed
first. Notably **NB-002 is the same class of bug** as the Ctrl+K issue fixed last session (inline
classic script touching a deferred-ESM global before it exists).

---

## SECTION 1 — Confirmed real bugs

### NB-001 — Alpine `@submit` handler crashes with `Unexpected token 'return'`
- **Severity:** High (client-side order validation never runs; console error on every submit).
- **Location:** [resources/views/tenant/orders/create.blade.php:19](resources/views/tenant/orders/create.blade.php#L19).
- **Root cause:** The form uses `@submit="return validateForm($event)"`. Alpine wraps an `x-on`
  expression as `return (<expr>)` before evaluating it, so this becomes
  `return (return validateForm($event))` → **double `return`** → `SyntaxError: Unexpected token 'return'`.
  The handler ([validateForm](resources/views/tenant/orders/create.blade.php#L234-L240)) already
  calls `e.preventDefault()` itself, so the leading `return` is both wrong and unnecessary.
- **Impact:** The advance-exceeds-total guard (`alert(...)`) never fires. Real damage is limited
  because the submit button is `:disabled="!canSubmit()"` and the server re-clamps advance via
  `min($advance, $total)` — but it throws a console error and the intended UX feedback is dead.
- **Fix:** `@submit="validateForm($event)"` (drop `return`). Optionally `@submit.prevent` + return early.

### NB-002 — `Sortable is not defined` (Kanban drag-and-drop broken)
- **Severity:** High (advertised drag-and-drop is completely non-functional).
- **Location:** [resources/views/tenant/orders/index.blade.php:140-151](resources/views/tenant/orders/index.blade.php#L140-L151)
  (inline `@push('scripts')` block); library is imported in [resources/js/app.js:12-13](resources/js/app.js#L12-L13).
- **Root cause:** **Identical to the Ctrl+K bug fixed last session.** The inline classic `<script>`
  runs during HTML parse and calls `new Sortable(col, …)`, but `window.Sortable` is defined by the
  `@vite` bundle, which is a **deferred `type="module"`** script that executes *after* parsing. So
  `Sortable` is undefined when the loop runs → `ReferenceError`. (The earlier lines in the same
  script — `updateStatus`, `.advance-btn` listeners — work because those DOM nodes already exist and
  don't touch `Sortable`.)
- **Fix:** Wrap the Sortable init in a `DOMContentLoaded` guard (so the deferred module has run) and
  bail if `!window.Sortable`, mirroring the [global-search.blade.php](resources/views/partials/global-search.blade.php) fix.

### NB-003 — Patient phone accepts free-text garbage
- **Severity:** Medium (data integrity).
- **Location:** [app/Http/Requests/StorePatientRequest.php:21-27](app/Http/Requests/StorePatientRequest.php#L21-L27).
- **Root cause:** The phone rule is `['required','string','max:30', Rule::unique(...)]` — no format
  validation, so `"abc-invalid-phone"` passes and persists.
- **Correction to the agent's claim:** It states "no duplicate detection." That is **wrong** — phone
  *is* unique per tenant (the `Rule::unique('patients')->where('tenant_id', …)`), and the friendly
  message exists. Only **format** validation is missing.
- **Fix:** Add a phone format rule, e.g. `'regex:/^[0-9+\-\s()]{7,15}$/'` (or an India-specific
  `/^[6-9]\d{9}$/` after stripping separators). Apply to both store and any future update path.

### NB-004 — Selling price can be saved below cost price (negative margin)
- **Severity:** Medium (financial integrity).
- **Location:** [app/Http/Requests/InventoryRequest.php:21-22](app/Http/Requests/InventoryRequest.php#L21-L22);
  form [resources/views/tenant/inventory/_form.blade.php:80-84](resources/views/tenant/inventory/_form.blade.php#L80-L84).
- **Root cause:** `cost_price` and `selling_price` are each validated `numeric|min:0` independently;
  there is no cross-field check that `selling_price >= cost_price`.
- **Fix:** Add `'selling_price' => [..., 'gte:cost_price']`. Consider a soft confirm-on-warn UX
  rather than a hard block (clearance items legitimately sell below cost), but at minimum surface it.

### NB-005 — A completely blank eye record can be saved
- **Severity:** Medium-Low (data quality; clutters patient timeline).
- **Location:** [app/Http/Requests/StoreEyeRecordRequest.php:16-32](app/Http/Requests/StoreEyeRecordRequest.php#L16-L32).
- **Root cause:** Every field is `nullable` with no "at least one measurement required" rule, so an
  all-empty submission creates an empty `eye_records` row.
- **Fix:** Add a `required_without_all` chain or a custom `after` validator requiring at least one of
  `od_sph`/`os_sph` (or any measurement) to be present.

### NB-007 — Quantity "−" (decrement) button renders invisibly
- **Severity:** Low (UX; "+" works, and qty also clamps, so not a hard blocker).
- **Location:** [resources/views/tenant/orders/create.blade.php:126](resources/views/tenant/orders/create.blade.php#L126).
- **Root cause (probable, needs a 2-min visual confirm):** The decrement button's label is the
  Unicode **MINUS SIGN `−` (U+2212)**, not an ASCII hyphen `-` (U+002D). The body font (Plus Jakarta
  Sans) may not include U+2212, so it falls back to a zero/near-zero-width glyph; the "+" (U+002B)
  always renders. The markup/classes for both buttons are otherwise identical, which rules out a
  layout cause.
- **Fix:** Use an ASCII `-`, a Bootstrap icon (`<i class="bi bi-dash"></i>`), or set an explicit
  min-width on the stepper buttons.

### NB-016 — Dashboard "Scan barcode" quick action is a misleading shortcut
- **Severity:** Low (UX expectation mismatch).
- **Location:** [resources/views/tenant/dashboard.blade.php:48](resources/views/tenant/dashboard.blade.php#L48).
- **Root cause:** The "Scan barcode" card routes to `tenant.inventory.index` (the plain inventory
  list). It opens no scan modal/camera/lookup. A scan endpoint exists
  ([InventoryController::scan](app/Http/Controllers/Tenant/InventoryController.php#L73)) but no UI
  surfaces it from the dashboard.
- **Fix:** Point the card at a dedicated scan modal (reuse the barcode-listener partial) or a
  `?scan=1` state on inventory that auto-opens a scan input.

---

## SECTION 2 — Confirmed real feature gaps

All verified absent in [routes/tenant.php](routes/tenant.php) and the relevant controllers.

| Ref | Gap | Evidence | Priority |
| --- | --- | --- | --- |
| NB-008 | **No edit for Patient profile** | Patient routes are only index/create/store/show; no `edit/update/destroy`. [patients/show.blade.php](resources/views/tenant/patients/show.blade.php) has no "Edit" button. | High |
| NB-008b | **No edit/delete for Eye Records** | Eye-record routes are only create/store. | High |
| FG-PaymentLog | **No "collect balance" / record additional payment** | Order has only `updateStatus`; no payment-recording action. Delivered orders with `balance_due > 0` are frozen. | High |
| NB-009 | **No cancel/void order** | No destroy/cancel route on orders; also means decremented stock is never restored (consistent with the earlier VERIFICATION_REPORT follow-up note). | High |
| FG-Settings | **No store/tenant settings page** | No settings route; store name/address/GSTIN/logo set only at onboarding, never editable. PDF receipts read from the DB with no UI to change it. | High |
| FG-Delete | **No delete for patients / inventory / orders** | No destroy routes anywhere → test rows (e.g. "abc-invalid-phone") can't be removed from the UI. | Medium |
| FG-OrderEdit | **Orders are immutable after creation** | No route to add/remove line items or change qty post-create. | Medium |
| FG-StockLog | **No manual stock-adjustment audit** | Stock changes only via the item edit form; no log/reason trail. | Medium |
| FG-Export | **No CSV/PDF export for inventory or patients** | Only the analytics ledger has an export ([LedgerExport](app/Exports/LedgerExport.php)). | Low-Med |

---

## SECTION 3 — False positives (already correct — do NOT "fix")

| Agent claim | Reality (evidence) |
| --- | --- |
| **UX-001** Quick-action cards have no hover state | They use `class="card card-lift …"` ([dashboard.blade.php:54](resources/views/tenant/dashboard.blade.php#L54)); `.card-lift` lifts -3px with `--shadow-raised` on hover per the design system. |
| **UX-005** SKU/Barcode editable on the edit page | Both are `<input … readonly>` in edit mode ([_form.blade.php:51-61](resources/views/tenant/inventory/_form.blade.php#L51-L61)); SKU/barcode are intentionally immutable. |
| **NB-003 (part)** "no duplicate phone detection" | Phone **is** unique per tenant via `Rule::unique('patients')->where('tenant_id', …)` with a friendly message. Only format validation is missing. |
| **FG-Pagination (part)** "no pagination on any table" | Patients and inventory **are** paginated (`paginate(50)`, with `{{ $patients->links() }}`). Only the **orders** view is unpaginated (it's a kanban/`groupBy`). |

---

## SECTION 4 — Real but UX-enhancement / by-design (lower priority)

| Ref | Item | Assessment |
| --- | --- | --- |
| NB-006 | Patient search not live (requires Enter) | By design: server-side search + pagination. The button is labeled **"Clear"**, not a "×" implying live filter. A debounced live search is a valid enhancement, not a defect. |
| UX-002 | Inventory columns not sortable | True; valid enhancement (add sortable headers / `?sort=`). |
| UX-003 | "Today's sales" counts delivered only | By design and **honestly labeled "Delivered orders"** ([DashboardController:19](app/Http/Controllers/Tenant/DashboardController.php#L19)). Could add a separate "placed today" metric. |
| UX-004 | Age/Gender show "—" when null | Cosmetic preference; consistent with the rest of the UI's em-dash convention. |
| UX-006 | No loading/skeleton states | True; server-rendered Blade. Nice-to-have (top progress bar / skeletons). |
| Premium (17-24) | Dark mode, shortcut overlay, revenue time-series, advanced filters, DOB-vs-age, prescription printout, multi-user roles, SMS/WhatsApp | All genuine roadmap items; none are defects. DOB-instead-of-age (FG-DOB) is a worthwhile data-model improvement. |

---

## Recommended fix order (when we move to implementation)

1. **NB-001 + NB-002** — JS runtime errors (quick, high-visibility). NB-002 reuses the prior
   `DOMContentLoaded` pattern. *(~30 min, front-end only.)*
2. **NB-003, NB-004, NB-005** — validation hardening in three Form Requests + a test each.
3. **NB-007, NB-016** — small Blade/UX fixes.
4. **Feature gaps (Section 2)** — scope as a proper CRUD-completeness milestone: patient edit, eye-
   record edit/delete, order cancel (+stock restore), collect-balance, store settings, deletes.
   Each new tenant-owned action needs a tenant-isolation test per `CLAUDE.md`.
5. **Section 4 enhancements** — backlog.

> Every code fix must keep `php artisan test` green (currently **85 passed**) and follow the
> design-token / tenant-scope conventions in `CLAUDE.md`. Front-end changes require `npm run build`.

---

## Scorecard — my adjustment

The agent's **5.3/10** "production readiness" is **harsher than warranted**. Two false positives and
one partly-wrong pagination claim inflated the "Form Usability" and "Workflow" penalties, and the
two JS errors are ~30 minutes of work, not architectural. A fairer read: **core platform is solid
(tenancy, pricing, stock, security all verified last session); the real gap is CRUD completeness
(edit/delete/cancel/payment) plus two small JS bugs.** Closing Section 1 + Section 2 would put this
comfortably in the 7.5–8/10 range.
