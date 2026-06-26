@extends('layouts.app')
@section('title', 'Orders')

@php
    $columns = [
        ['key' => 'pending', 'title' => 'Pending', 'desc' => 'In the lab', 'icon' => 'bi-clock-history'],
        ['key' => 'ready_for_pickup', 'title' => 'Ready for Pickup', 'desc' => 'Waiting for customer', 'icon' => 'bi-bag-check'],
        ['key' => 'delivered', 'title' => 'Delivered', 'desc' => 'Completed', 'icon' => 'bi-check-circle'],
    ];
@endphp

@section('content')
<div class="p-4 p-md-5">
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between mb-4">
        <div>
            <p class="section-label mb-1">Workspace</p>
            <h1 class="h3 fw-semibold font-display mb-1">Orders</h1>
            <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
                Drag a card or use the button to advance an order through the workflow.
            </p>
        </div>
        <a href="{{ route('tenant.orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> New order</a>
    </div>

    <div class="row g-3">
        @foreach ($columns as $col)
            @php $colOrders = $orders[$col['key']] ?? collect(); @endphp
            <div class="col-md-4">
                <div class="d-flex align-items-center justify-content-between px-1 mb-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary"
                              style="width:1.75rem;height:1.75rem;"><i class="bi {{ $col['icon'] }}"></i></span>
                        <div>
                            <p class="mb-0 fw-semibold font-display" style="font-size:.9rem;">{{ $col['title'] }}</p>
                            <p class="mb-0 text-muted-foreground" style="font-size:.7rem;">{{ $col['desc'] }}</p>
                        </div>
                    </div>
                    <span class="badge text-bg-light">{{ $colOrders->count() }}</span>
                </div>

                <div class="kanban-column" data-status="{{ $col['key'] }}">
                    @forelse ($colOrders as $order)
                        <div class="kanban-card card border-0 shadow-sm rounded-3 mb-2" data-id="{{ $order->id }}">
                            <div class="card-body p-3">
                                <a href="{{ route('tenant.orders.show', $order) }}" class="text-decoration-none text-reset d-block">
                                    <p class="mb-0 fw-medium text-truncate">{{ $order->patient?->name ?? 'Unknown patient' }}</p>
                                    @if ($order->patient?->phone)
                                        <p class="mb-2 text-muted-foreground" style="font-size:.72rem;">
                                            <i class="bi bi-telephone me-1"></i>{{ $order->patient->phone }}
                                        </p>
                                    @endif
                                    <div class="row g-1 text-center" style="font-size:.72rem;">
                                        <div class="col-4">
                                            <div class="text-muted-foreground text-uppercase" style="font-size:.6rem;">Total</div>
                                            <div class="font-monospace">₹{{ number_format($order->total_amount, 0) }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted-foreground text-uppercase" style="font-size:.6rem;">Balance</div>
                                            <div class="font-monospace {{ $order->balance_due > 0 ? 'text-danger' : '' }}">₹{{ number_format($order->balance_due, 0) }}</div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-muted-foreground text-uppercase" style="font-size:.6rem;">Items</div>
                                            <div class="font-monospace">{{ $order->items_count }}</div>
                                        </div>
                                    </div>
                                    <p class="mb-0 mt-2 text-muted-foreground" style="font-size:.68rem;">
                                        Placed {{ $order->created_at->format('d M Y') }}
                                    </p>
                                </a>
                                @if ($col['key'] !== 'delivered')
                                    @php $next = $col['key'] === 'pending' ? 'ready_for_pickup' : 'delivered'; @endphp
                                    <button type="button" class="btn btn-primary btn-sm w-100 mt-2 advance-btn"
                                            data-id="{{ $order->id }}" data-next="{{ $next }}">
                                        {{ $col['key'] === 'pending' ? 'Mark ready' : 'Mark delivered' }}
                                        <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-center text-muted-foreground border border-2 border-dashed rounded-3 py-4 mb-0"
                           style="font-size:.8rem;">Nothing here yet</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>

@push('scripts')
<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const STATUS_URL = (id) => `{{ url('tenant/orders') }}/${id}/status`;

    function updateStatus(id, status) {
        return fetch(STATUS_URL(id), {
            method: 'PATCH',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ status }),
        });
    }

    // Drag-and-drop between columns
    document.querySelectorAll('.kanban-column').forEach((col) => {
        new Sortable(col, {
            group: 'orders', animation: 150, ghostClass: 'sortable-ghost',
            onEnd(evt) {
                const newStatus = evt.to.dataset.status;
                const id = evt.item.dataset.id;
                if (newStatus !== evt.from.dataset.status) {
                    updateStatus(id, newStatus).then(() => window.location.reload());
                }
            },
        });
    });

    // Advance button
    document.querySelectorAll('.advance-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            btn.disabled = true;
            updateStatus(btn.dataset.id, btn.dataset.next).then(() => window.location.reload());
        });
    });
</script>
@endpush
@endsection
