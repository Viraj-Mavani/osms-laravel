@extends('layouts.app')
@section('title', 'New order')

@section('content')
<div class="p-4 p-md-5" x-data="orderBuilder()" x-init="init()">
    <a href="{{ route('tenant.orders.index') }}"
       class="d-inline-flex align-items-center gap-1 text-muted-foreground text-decoration-none mb-3" style="font-size:.8rem;">
        <i class="bi bi-chevron-left"></i> Back to orders
    </a>
    <p class="section-label mb-1">New order</p>
    <h1 class="h3 fw-semibold font-display mb-4">Create order</h1>

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 small rounded-3">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.orders.store') }}" @submit="return validateForm($event)">
        @csrf
        <input type="hidden" name="patient_id" :value="patientId">
        <input type="hidden" name="eye_record_id" :value="eyeRecordId">
        <input type="hidden" name="advance_paid" :value="advancePaid">
        <template x-for="(it, idx) in items" :key="it.inventory_id">
            <span>
                <input type="hidden" :name="`items[${idx}][inventory_id]`" :value="it.inventory_id">
                <input type="hidden" :name="`items[${idx}][quantity]`" :value="it.quantity">
            </span>
        </template>

        <div class="row g-4">
            {{-- Left column --}}
            <div class="col-lg-8 d-flex flex-column gap-4">
                {{-- Patient picker --}}
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <h2 class="section-label mb-3">Patient</h2>
                        <div x-show="!patientId" class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
                                <input type="text" class="form-control" placeholder="Search patient by name or phone…"
                                       x-model="patientSearch" data-barcode-target>
                            </div>
                            <div class="list-group position-absolute w-100 shadow-sm" style="z-index:5;"
                                 x-show="patientSearch.length > 0 && filteredPatients().length">
                                <template x-for="p in filteredPatients()" :key="p.id">
                                    <button type="button" class="list-group-item list-group-item-action"
                                            @click="selectPatient(p)">
                                        <span class="fw-medium" x-text="p.name"></span>
                                        <span class="text-muted-foreground small" x-text="' · ' + p.phone"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="patientId" class="d-flex align-items-center justify-content-between">
                            <div>
                                <p class="mb-0 fw-medium" x-text="selectedPatient?.name"></p>
                                <p class="mb-0 text-muted-foreground small" x-text="selectedPatient?.phone"></p>
                            </div>
                            <button type="button" class="btn btn-sm btn-light" @click="clearPatient()">Change</button>
                        </div>

                        {{-- Eye record select --}}
                        <div x-show="patientId && eyeRecords.length" class="mt-3">
                            <label class="form-label small fw-medium mb-1">Attach prescription (optional)</label>
                            <select class="form-select" x-model="eyeRecordId">
                                <option value="">No prescription</option>
                                <template x-for="r in eyeRecords" :key="r.id">
                                    <option :value="r.id" x-text="r.label"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Line items --}}
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-body p-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h2 class="section-label mb-0">Line items</h2>
                            <span class="badge text-bg-light"><i class="bi bi-upc-scan me-1"></i>Scanner ready</span>
                        </div>

                        <p x-show="scanFlash" x-text="scanFlash"
                           class="bg-primary-subtle text-primary rounded-3 px-3 py-2 small mb-3"></p>

                        <div class="position-relative mb-3">
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
                                <input type="text" class="form-control" placeholder="Type to search inventory, or scan a barcode…"
                                       x-model="itemSearch" data-barcode-target>
                            </div>
                            <div class="list-group position-absolute w-100 shadow-sm" style="z-index:5;"
                                 x-show="itemSearch.length > 0 && filteredInventory().length">
                                <template x-for="inv in filteredInventory()" :key="inv.id">
                                    <button type="button" class="list-group-item list-group-item-action d-flex justify-content-between"
                                            @click="addItem(inv); itemSearch=''">
                                        <span>
                                            <span class="fw-medium" x-text="inv.brand || '—'"></span>
                                            <span class="text-muted-foreground" x-text="inv.model_name"></span>
                                            <span class="d-block text-muted-foreground" style="font-size:.72rem;"
                                                  x-text="inv.sku + ' · stock ' + inv.stock_qty"></span>
                                        </span>
                                        <span class="font-monospace small" x-text="money(inv.selling_price)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>

                        <div x-show="items.length === 0" class="text-center text-muted-foreground py-4 border border-2 border-dashed rounded-3">
                            No items yet — search or scan to add.
                        </div>

                        <div class="table-responsive" x-show="items.length">
                            <table class="table align-middle mb-0">
                                <thead class="text-muted-foreground" style="font-size:.75rem;">
                                    <tr><th>Item</th><th style="width:8rem;">Qty</th><th class="text-end">Unit</th><th class="text-end">Total</th><th></th></tr>
                                </thead>
                                <tbody>
                                    <template x-for="it in items" :key="it.inventory_id">
                                        <tr>
                                            <td x-text="it.label"></td>
                                            <td>
                                                <div class="input-group input-group-sm" style="width:7rem;">
                                                    <button type="button" class="btn btn-outline-secondary" @click="changeQty(it,-1)">−</button>
                                                    <input type="text" class="form-control text-center" :value="it.quantity" readonly>
                                                    <button type="button" class="btn btn-outline-secondary" @click="changeQty(it,1)">+</button>
                                                </div>
                                            </td>
                                            <td class="text-end font-monospace" x-text="money(it.unit_price)"></td>
                                            <td class="text-end font-monospace" x-text="money(it.unit_price*it.quantity)"></td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-link text-danger p-0" @click="removeItem(it)">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Summary --}}
            <div class="col-lg-4">
                <div class="glass card-lift rounded-4 p-4 position-sticky" style="top:1.5rem;">
                    <h2 class="section-label mb-3">Summary</h2>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted-foreground">Items</span>
                        <span class="fw-medium" x-text="itemCount()"></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted-foreground">Total</span>
                        <span class="fw-medium font-monospace" x-text="money(total())"></span>
                    </div>
                    <div class="border-top mt-3 pt-3">
                        <label class="form-label small fw-medium mb-1">Advance paid (₹)</label>
                        <input type="number" step="0.01" min="0" class="form-control" x-model="advancePaid">
                    </div>
                    <div class="bg-primary-subtle rounded-3 p-3 mt-3">
                        <p class="text-uppercase text-primary mb-1" style="font-size:.68rem;letter-spacing:.05em;">Balance due</p>
                        <p class="h4 fw-semibold font-display mb-0" x-text="money(balance())"></p>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3" :disabled="!canSubmit()">
                        <i class="bi bi-plus-lg me-1"></i> Create order
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
@include('partials.barcode-listener', ['onScan' => 'orderScan'])
<script>
    window.orderScan = (code) => window.dispatchEvent(new CustomEvent('osms-barcode', { detail: code }));

    function orderBuilder() {
        return {
            patients: @json($patients),
            inventory: @json($inventory),
            patientId: '', selectedPatient: null, patientSearch: '',
            eyeRecords: [], eyeRecordId: '',
            items: [], itemSearch: '', scanFlash: null,
            advancePaid: '0',

            init() {
                const pre = @json($selectedPatientId);
                if (pre) { const p = this.patients.find(x => x.id === pre); if (p) this.selectPatient(p); }
                window.addEventListener('osms-barcode', (e) => this.onScan(e.detail));
            },
            money(n) { return '₹ ' + Number(n).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2}); },
            filteredPatients() {
                const q = this.patientSearch.trim().toLowerCase();
                if (!q) return [];
                return this.patients.filter(p => (p.name+' '+p.phone).toLowerCase().includes(q)).slice(0,6);
            },
            filteredInventory() {
                const q = this.itemSearch.trim().toLowerCase();
                if (!q) return [];
                return this.inventory.filter(i =>
                    [i.brand,i.model_name,i.sku,i.barcode].some(v => v && v.toLowerCase().includes(q))).slice(0,6);
            },
            selectPatient(p) {
                this.patientId = p.id; this.selectedPatient = p; this.patientSearch = '';
                fetch(`{{ url('tenant/patients') }}/${p.id}/eye-records`, {headers:{'Accept':'application/json'}})
                    .then(r => r.json()).then(d => { this.eyeRecords = d; this.eyeRecordId = d[0]?.id || ''; });
            },
            clearPatient() { this.patientId=''; this.selectedPatient=null; this.eyeRecords=[]; this.eyeRecordId=''; },
            addItem(inv, qty=1) {
                const ex = this.items.find(i => i.inventory_id === inv.id);
                if (ex) { ex.quantity = Math.min(ex.max_stock, ex.quantity + qty); return; }
                this.items.push({
                    inventory_id: inv.id,
                    label: (inv.brand||'—') + (inv.model_name ? ' · '+inv.model_name : ''),
                    unit_price: Number(inv.selling_price), quantity: Math.min(inv.stock_qty, qty), max_stock: inv.stock_qty,
                });
            },
            changeQty(it, delta) { it.quantity = Math.min(Math.max(1, it.quantity + delta), it.max_stock); },
            removeItem(it) { this.items = this.items.filter(i => i.inventory_id !== it.inventory_id); },
            onScan(code) {
                const m = this.inventory.find(i => i.barcode === code || i.sku === code);
                if (m) { this.addItem(m,1); this.flash(`Added ${m.brand||'item'} ${m.model_name||''}`.trim()); }
                else { this.flash(`No item matches "${code}"`); }
            },
            flash(msg) { this.scanFlash = msg; setTimeout(()=> this.scanFlash=null, 2000); },
            total() { return this.items.reduce((s,i)=> s + i.unit_price*i.quantity, 0); },
            itemCount() { return this.items.reduce((s,i)=> s + i.quantity, 0); },
            balance() { return Math.max(this.total() - (Number(this.advancePaid)||0), 0); },
            canSubmit() { return this.patientId && this.items.length > 0; },
            validateForm(e) {
                if (!this.canSubmit()) { e.preventDefault(); return false; }
                if ((Number(this.advancePaid)||0) > this.total()) {
                    e.preventDefault(); alert('Advance cannot exceed the total amount.'); return false;
                }
                return true;
            },
        };
    }
</script>
@endpush
@endsection
