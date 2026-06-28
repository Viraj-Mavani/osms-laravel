@extends('layouts.app')
@section('title', 'Inventory')

@section('content')
<div class="p-4 p-md-5">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between mb-4">
        <div>
            <p class="section-label mb-1">Workspace</p>
            <h1 class="h3 fw-semibold font-display mb-1">Inventory</h1>
            <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
                Frames, lenses, and accessories — scan a barcode anywhere to find an item.
            </p>
        </div>
        <a href="{{ route('tenant.inventory.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> Add item
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('tenant.inventory.index') }}" id="filterForm" class="d-flex flex-wrap gap-2 mb-4">
        <div class="input-group flex-grow-1" style="min-width:16rem;max-width:28rem;">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
            <input id="searchInput" type="search" name="q" value="{{ $q }}" class="form-control"
                   placeholder="Brand, model, SKU, or barcode…" autocomplete="off">
        </div>
        <select name="type" class="form-select w-auto" onchange="document.getElementById('filterForm').submit()">
            <option value="">All types</option>
            <option value="frame" @selected($type==='frame')>Frames</option>
            <option value="lens" @selected($type==='lens')>Lenses</option>
            <option value="contact_lens" @selected($type==='contact_lens')>Contact lenses</option>
            <option value="accessory" @selected($type==='accessory')>Accessories</option>
        </select>
        <select name="stock" class="form-select w-auto" onchange="document.getElementById('filterForm').submit()">
            <option value="">Any stock</option>
            <option value="low" @selected($stock==='low')>Low stock</option>
            <option value="out" @selected($stock==='out')>Out of stock</option>
        </select>
        <button type="submit" class="btn btn-secondary">Search</button>
        @if ($q || $type || $stock)
            <a href="{{ route('tenant.inventory.index') }}" class="btn btn-secondary"><i class="bi bi-x-lg"></i></a>
        @endif
    </form>

    @if ($items->isNotEmpty())
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-muted-foreground text-uppercase" style="font-size:.72rem;letter-spacing:.04em;">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th>Type</th>
                            <th>SKU / Barcode</th>
                            <th class="text-end">Cost</th>
                            <th class="text-end">Sell</th>
                            <th class="text-end pe-4">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $i)
                            @php $isOut = $i->stock_qty === 0; $isLow = !$isOut && $i->stock_qty <= $i->min_alert_qty; @endphp
                            <tr role="button" onclick="window.location='{{ route('tenant.inventory.edit', $i) }}'"
                                class="{{ $isOut ? 'table-danger' : ($isLow ? 'table-warning' : '') }}">
                                <td class="ps-4">
                                    <span class="fw-medium">{{ $i->brand ?? '—' }}</span>
                                    <span class="text-muted-foreground">{{ $i->model_name }}</span>
                                </td>
                                <td><span class="badge text-bg-light">{{ $i->type_label }}</span></td>
                                <td class="font-monospace text-muted-foreground" style="font-size:.78rem;">
                                    <div>{{ $i->sku }}</div>
                                    <div style="font-size:.68rem;opacity:.7;">{{ $i->barcode }}</div>
                                </td>
                                <td class="text-end text-muted-foreground">₹ {{ number_format($i->cost_price, 2) }}</td>
                                <td class="text-end">₹ {{ number_format($i->selling_price, 2) }}</td>
                                <td class="text-end pe-4">
                                    <span class="fw-medium {{ $isOut ? 'text-danger' : ($isLow ? 'text-warning' : '') }}">
                                        @if($isOut)<i class="bi bi-x-circle"></i>@elseif($isLow)<i class="bi bi-exclamation-triangle"></i>@endif
                                        {{ $i->stock_qty }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($items->hasPages())
            <div class="mt-3">{{ $items->links() }}</div>
        @endif
    @else
        <div class="glass card-lift rounded-4 text-center p-5">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3"
                  style="width:3rem;height:3rem;"><i class="bi bi-box-seam fs-4"></i></span>
            <h2 class="h5 fw-semibold font-display">
                {{ ($q || $type || $stock) ? 'No items match your filters' : 'No inventory yet' }}
            </h2>
            <p class="text-muted-foreground mb-3">
                {{ ($q || $type || $stock) ? 'Try adjusting your search or filters.' : 'Add your first frame or lens to get started.' }}
            </p>
            @unless ($q || $type || $stock)
                <a href="{{ route('tenant.inventory.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add item</a>
            @endunless
        </div>
    @endif
</div>

@push('scripts')
@include('partials.barcode-listener', ['onScan' => "fillSearch"])
<script>
    // Scanner fills the search box and submits (mirrors the original InventoryFilters).
    function fillSearch(code) {
        const input = document.getElementById('searchInput');
        if (input) { input.value = code; document.getElementById('filterForm').submit(); }
    }
</script>
@endpush
@endsection
