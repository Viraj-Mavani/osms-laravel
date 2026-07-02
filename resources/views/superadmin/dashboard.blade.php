@extends('layouts.app')
@section('title', 'Platform')

@section('content')
<div class="p-4 p-md-5">
    <div class="mb-4">
        <p class="section-label mb-1">Platform</p>
        <h1 class="h3 fw-semibold font-display mb-1">Superadmin</h1>
        <p class="text-muted-foreground mb-0" style="font-size:.9rem;">Overview of all stores on OSMS.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-lg-3">
            <x-metric-card label="Stores" value="{{ $stats['tenants'] }}" icon="bi-shop" tone="primary" />
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card label="Users" value="{{ $stats['users'] }}" icon="bi-people" />
        </div>
        <div class="col-6 col-lg-3">
            <x-metric-card label="Orders (all)" value="{{ $stats['orders'] }}" icon="bi-cart3" />
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-header bg-transparent border-0 pt-3 px-4">
            <h2 class="h6 fw-semibold mb-0">Stores</h2>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="text-muted-foreground" style="font-size:.78rem;">
                    <tr>
                        <th class="ps-4">Store</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th class="text-end">Users</th>
                        <th class="text-end">Customers</th>
                        <th class="text-end pe-4">Orders</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tenants as $t)
                        <tr>
                            <td class="ps-4">
                                <div class="fw-medium">{{ $t->store_name }}</div>
                                <div class="text-muted-foreground" style="font-size:.75rem;">{{ $t->tax_id }}</div>
                            </td>
                            <td class="text-capitalize">{{ $t->subscription?->tier ?? '—' }}</td>
                            <td>
                                @php $s = $t->subscription?->status; @endphp
                                <span class="badge {{ $s === 'active' || $s === 'trialing' ? 'text-bg-success' : ($s === 'past_due' ? 'text-bg-warning' : 'text-bg-secondary') }}">
                                    {{ $s ?? 'none' }}
                                </span>
                            </td>
                            <td class="text-end">{{ $t->users_count }}</td>
                            <td class="text-end">{{ $t->customers_count }}</td>
                            <td class="text-end pe-4">{{ $t->orders_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-foreground py-4">No stores yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
