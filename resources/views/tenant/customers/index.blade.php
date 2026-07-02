@extends('layouts.app')
@section('title', 'Customers')

@section('content')
<div class="p-4 p-md-5">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between mb-4">
        <div>
            <p class="section-label mb-1">Workspace</p>
            <h1 class="h3 fw-semibold font-display mb-1">Customers</h1>
            <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
                Manage customer profiles, prescriptions, and order history.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tenant.customers.export', ['q' => $search]) }}" class="btn btn-light">
                <i class="bi bi-download me-1"></i> Export
            </a>
            <a href="{{ route('tenant.customers.trash') }}" class="btn btn-light">
                <i class="bi bi-archive me-1"></i> Archive
            </a>
            <a href="{{ route('tenant.customers.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New customer
            </a>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('tenant.customers.index') }}" class="mb-4" style="max-width:28rem;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
            <input type="search" name="q" value="{{ $search }}" class="form-control"
                   placeholder="Search by name or phone…" autocomplete="off">
            @if ($search)
                <a href="{{ route('tenant.customers.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </div>
    </form>

    @if ($customers->isNotEmpty())
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="text-muted-foreground" style="font-size:.78rem;">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Phone</th>
                            <th>Age</th>
                            <th>Gender</th>
                            <th class="pe-4">Added</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($customers as $c)
                            <tr class="search-result-item" role="button"
                                onclick="window.location='{{ route('tenant.customers.show', $c) }}'">
                                <td class="ps-4 fw-medium">{{ $c->name }}</td>
                                <td>{{ $c->phone }}</td>
                                <td>{{ $c->age ?? '—' }}</td>
                                <td class="text-capitalize">{{ $c->gender ?? '—' }}</td>
                                <td class="pe-4 text-muted-foreground" style="font-size:.82rem;">
                                    {{ $c->created_at->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($customers->hasPages())
            <div class="mt-3">{{ $customers->links() }}</div>
        @endif
    @else
        <div class="glass card-lift rounded-4 text-center p-5">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3"
                  style="width:3rem;height:3rem;"><i class="bi bi-people fs-4"></i></span>
            <h2 class="h5 fw-semibold font-display">
                {{ $search ? 'No customers match your search' : 'No customers yet' }}
            </h2>
            <p class="text-muted-foreground mb-3">
                {{ $search ? 'Try a different name or phone number.' : 'Add your first customer to start tracking prescriptions and orders.' }}
            </p>
            @unless ($search)
                <a href="{{ route('tenant.customers.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add your first customer
                </a>
            @endunless
        </div>
    @endif
</div>
@endsection
