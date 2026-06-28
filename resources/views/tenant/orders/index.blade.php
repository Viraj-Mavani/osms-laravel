@extends('layouts.app')
@section('title', 'Orders')

@php
    // Status presentation: dot colour + badge classes + human label.
    $statusMeta = [
        'pending'          => ['label' => 'In lab',  'badge' => 'osms-badge-amber',   'dot' => '#b45309'],
        'ready_for_pickup' => ['label' => 'Ready',   'badge' => 'osms-badge-blue',    'dot' => '#004f75'],
        'delivered'        => ['label' => 'Delivered','badge' => 'osms-badge-green',   'dot' => '#15803d'],
    ];

@endphp

@section('content')
<div class="p-4 p-md-5">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between mb-4">
        <div>
            <p class="section-label mb-1">Workspace</p>
            <h1 class="h3 fw-semibold font-display mb-1">Orders</h1>
            <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
                Find, filter, and advance orders through the lab workflow.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            {{-- View toggle --}}
            <div class="btn-group" role="group" aria-label="View mode">
                <a href="{{ route('tenant.orders.index') }}"
                   class="btn btn-sm {{ $view === 'table' ? 'btn-primary' : 'btn-secondary' }}">
                    <i class="bi bi-table me-1"></i> Table
                </a>
                <a href="{{ route('tenant.orders.index', ['view' => 'kanban']) }}"
                   class="btn btn-sm {{ $view === 'kanban' ? 'btn-primary' : 'btn-secondary' }}">
                    <i class="bi bi-kanban me-1"></i> Board
                </a>
            </div>
            <a href="{{ route('tenant.orders.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-lg me-1"></i> New order
            </a>
        </div>
    </div>

    {{-- KPI stat cards (clickable → filter the table) --}}
    @php $statBase = ['view' => null]; @endphp
    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <a href="{{ route('tenant.orders.index') }}"
               class="osms-stat card border-0 shadow-sm rounded-4 h-100 text-reset text-decoration-none {{ $view==='table' && empty($status) && empty($payment) ? 'osms-stat-active' : '' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="osms-stat-icon osms-stat-icon-neutral"><i class="bi bi-receipt"></i></span>
                    <div>
                        <div class="osms-stat-value font-display">{{ number_format($stats['total']) }}</div>
                        <div class="osms-stat-label">Total orders</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('tenant.orders.index', ['status' => 'pending']) }}"
               class="osms-stat card border-0 shadow-sm rounded-4 h-100 text-reset text-decoration-none {{ ($status ?? '')==='pending' ? 'osms-stat-active' : '' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="osms-stat-icon osms-stat-icon-amber"><i class="bi bi-clock-history"></i></span>
                    <div>
                        <div class="osms-stat-value font-display">{{ number_format($stats['pending']) }}</div>
                        <div class="osms-stat-label">In lab</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('tenant.orders.index', ['status' => 'ready_for_pickup']) }}"
               class="osms-stat card border-0 shadow-sm rounded-4 h-100 text-reset text-decoration-none {{ ($status ?? '')==='ready_for_pickup' ? 'osms-stat-active' : '' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="osms-stat-icon osms-stat-icon-blue"><i class="bi bi-bag-check"></i></span>
                    <div>
                        <div class="osms-stat-value font-display">{{ number_format($stats['ready']) }}</div>
                        <div class="osms-stat-label">Ready for pickup</div>
                    </div>
                </div>
            </a>
        </div>
        <div class="col-6 col-lg-3">
            <a href="{{ route('tenant.orders.index', ['payment' => 'outstanding']) }}"
               class="osms-stat card border-0 shadow-sm rounded-4 h-100 text-reset text-decoration-none {{ ($payment ?? '')==='outstanding' ? 'osms-stat-active' : '' }}">
                <div class="card-body d-flex align-items-center gap-3">
                    <span class="osms-stat-icon osms-stat-icon-red"><i class="bi bi-cash-coin"></i></span>
                    <div>
                        <div class="osms-stat-value font-display">₹{{ number_format($stats['outstanding'], 0) }}</div>
                        <div class="osms-stat-label">Outstanding</div>
                    </div>
                </div>
            </a>
        </div>
    </div>

    @if ($view === 'kanban')
        @include('tenant.orders.partials.kanban', ['statusMeta' => $statusMeta])
    @else
        @include('tenant.orders.partials.table', ['statusMeta' => $statusMeta])
    @endif
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

    // Inline quick-advance (table + kanban share this).
    document.querySelectorAll('.advance-btn').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            btn.disabled = true;
            btn.querySelector('.spinner-border')?.classList.remove('d-none');
            updateStatus(btn.dataset.id, btn.dataset.next).then(() => window.location.reload());
        });
    });

    // Keyboard: "/" focuses search (when present, table view).
    const searchInput = document.getElementById('orderSearch');
    if (searchInput) {
        document.addEventListener('keydown', (e) => {
            if (e.key === '/' && document.activeElement !== searchInput
                && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
                e.preventDefault();
                searchInput.focus();
            }
        });
    }

    // Kanban drag-and-drop (only present in board view).
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
</script>
@endpush
@endsection
