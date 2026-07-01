@php
    // Sort header link that flips direction on the active column.
    $sortLink = function ($key, $label) use ($sort, $dir) {
        $nextDir = ($sort === $key && $dir === 'asc') ? 'desc' : 'asc';
        $icon = $sort !== $key ? 'bi-arrow-down-up opacity-25'
              : ($dir === 'asc' ? 'bi-arrow-up' : 'bi-arrow-down');
        $params = array_merge(request()->query(), ['sort' => $key, 'dir' => $nextDir]);
        $url = route('tenant.orders.index') . '?' . http_build_query($params);
        return '<a href="' . $url . '" class="text-reset text-decoration-none d-inline-flex align-items-center gap-1">'
             . e($label) . ' <i class="bi ' . $icon . '" style="font-size:.7rem;"></i></a>';
    };
@endphp

{{-- Filter / search toolbar --}}
<form method="GET" action="{{ route('tenant.orders.index') }}" id="orderFilters" class="d-flex flex-wrap gap-2 mb-3">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="dir" value="{{ $dir }}">
    <div class="input-group flex-grow-1" style="min-width:16rem;max-width:26rem;">
        <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
        <input id="orderSearch" type="search" name="q" value="{{ $search }}" class="form-control"
               placeholder="Search patient name or phone…  ( / )" autocomplete="off">
    </div>
    <select name="status" class="form-select w-auto" onchange="document.getElementById('orderFilters').submit()">
        <option value="">All statuses</option>
        <option value="pending"          @selected($status==='pending')>In lab</option>
        <option value="ready_for_pickup" @selected($status==='ready_for_pickup')>Ready for pickup</option>
        <option value="delivered"        @selected($status==='delivered')>Delivered</option>
        <option value="cancelled"        @selected($status==='cancelled')>Cancelled</option>
    </select>
    <select name="payment" class="form-select w-auto" onchange="document.getElementById('orderFilters').submit()">
        <option value="">Any payment</option>
        <option value="outstanding" @selected($payment==='outstanding')>Balance due</option>
        <option value="paid"        @selected($payment==='paid')>Fully paid</option>
    </select>
    <button type="submit" class="btn btn-secondary">Search</button>
    @if ($search || $status || $payment)
        <a href="{{ route('tenant.orders.index') }}" class="btn btn-secondary" title="Clear filters"><i class="bi bi-x-lg"></i></a>
    @endif
</form>

@if ($orders->isNotEmpty())
    <div class="card border-0 shadow-sm rounded-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0 osms-orders-table">
                <thead class="text-muted-foreground text-uppercase" style="font-size:.72rem;letter-spacing:.04em;">
                    <tr>
                        <th class="ps-4">Order / Patient</th>
                        <th>Status</th>
                        <th class="text-end">{!! $sortLink('total_amount', 'Total') !!}</th>
                        <th class="text-end">{!! $sortLink('balance_due', 'Balance') !!}</th>
                        <th class="text-center d-none d-lg-table-cell">Items</th>
                        <th class="d-none d-md-table-cell">{!! $sortLink('created_at', 'Placed') !!}</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        @php
                            $meta = $statusMeta[$order->status] ?? ['label' => $order->status_label, 'badge' => 'osms-badge-blue', 'dot' => '#6b7785'];
                            $num = strtoupper(substr($order->id, 0, 8));
                            $next = $order->status === 'pending' ? 'ready_for_pickup'
                                  : ($order->status === 'ready_for_pickup' ? 'delivered' : null);
                            $nextLabel = $order->status === 'pending' ? 'Mark ready' : 'Deliver';
                        @endphp
                        <tr role="button" onclick="window.location='{{ route('tenant.orders.show', $order) }}'">
                            <td class="ps-4">
                                <div class="fw-semibold text-truncate" style="max-width:14rem;">
                                    {{ $order->patient?->name ?? 'Unknown patient' }}
                                </div>
                                <div class="text-muted-foreground d-flex align-items-center gap-2" style="font-size:.74rem;">
                                    <span class="font-monospace">#{{ $num }}</span>
                                    @if ($order->patient?->phone)
                                        <span><i class="bi bi-telephone me-1"></i>{{ $order->patient->phone }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span class="osms-badge {{ $meta['badge'] }}">
                                    <span class="osms-badge-dot" style="background:{{ $meta['dot'] }};"></span>
                                    {{ $meta['label'] }}
                                </span>
                            </td>
                            <td class="text-end font-monospace">₹{{ number_format($order->total_amount, 0) }}</td>
                            <td class="text-end font-monospace">
                                @if ($order->balance_due > 0)
                                    <span class="text-danger fw-medium">₹{{ number_format($order->balance_due, 0) }}</span>
                                @else
                                    <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>Paid</span>
                                @endif
                            </td>
                            <td class="text-center d-none d-lg-table-cell text-muted-foreground">{{ $order->items_count }}</td>
                            <td class="d-none d-md-table-cell text-muted-foreground" style="font-size:.82rem;">
                                {{ $order->created_at->format('d M Y') }}
                            </td>
                            <td class="pe-4" onclick="event.stopPropagation()">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    @if ($next)
                                        <button type="button" class="btn btn-sm btn-primary advance-btn"
                                                data-id="{{ $order->id }}" data-next="{{ $next }}">
                                            <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                                            {{ $nextLabel }}<i class="bi bi-arrow-right ms-1"></i>
                                        </button>
                                    @endif
                                    <a href="{{ route('tenant.orders.pdf', $order) }}" target="_blank"
                                       class="btn btn-sm btn-light" title="PDF receipt">
                                        <i class="bi bi-file-earmark-pdf"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-2 mt-3">
        <p class="text-muted-foreground mb-0" style="font-size:.82rem;">
            Showing {{ $orders->firstItem() }}–{{ $orders->lastItem() }} of {{ number_format($orders->total()) }} orders
        </p>
        @if ($orders->hasPages())
            <div>{{ $orders->links() }}</div>
        @endif
    </div>
@else
    <div class="glass card-lift rounded-4 text-center p-5">
        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3"
              style="width:3rem;height:3rem;"><i class="bi bi-bag fs-4"></i></span>
        <h2 class="h5 fw-semibold font-display">
            {{ ($search || $status || $payment) ? 'No orders match your filters' : 'No orders yet' }}
        </h2>
        <p class="text-muted-foreground mb-3">
            {{ ($search || $status || $payment) ? 'Try adjusting your search, status, or payment filters.' : 'Create your first order to start tracking the lab workflow.' }}
        </p>
        @if ($search || $status || $payment)
            <a href="{{ route('tenant.orders.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i> Clear filters</a>
        @else
            <a href="{{ route('tenant.orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New order</a>
        @endif
    </div>
@endif
