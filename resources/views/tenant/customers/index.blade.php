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

    {{-- Search + role filter --}}
    <div class="d-flex flex-column flex-sm-row gap-3 align-items-sm-center justify-content-between mb-4">
        <form method="GET" action="{{ route('tenant.customers.index') }}" style="max-width:28rem;flex:1;">
            @if ($filter)<input type="hidden" name="filter" value="{{ $filter }}">@endif
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
                <input type="search" name="q" value="{{ $search }}" class="form-control"
                       placeholder="Search by name or phone…" autocomplete="off">
                @if ($search)
                    <a href="{{ route('tenant.customers.index', ['filter' => $filter ?: null]) }}" class="btn btn-secondary">Clear</a>
                @endif
            </div>
        </form>

        <div class="btn-group" role="group" aria-label="Filter by role">
            <a href="{{ route('tenant.customers.index', ['q' => $search ?: null]) }}"
               class="btn btn-sm {{ $filter === '' ? 'btn-primary' : 'btn-light' }}">All</a>
            <a href="{{ route('tenant.customers.index', ['q' => $search ?: null, 'filter' => 'patients']) }}"
               class="btn btn-sm {{ $filter === 'patients' ? 'btn-primary' : 'btn-light' }}">Patients</a>
        </div>
    </div>

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
                                <td class="ps-4 fw-medium">
                                    {{ $c->name }}
                                    @if ($c->eye_records_count > 0)
                                        <span class="osms-badge osms-badge-blue ms-1" title="Has a prescription on file">
                                            <span class="osms-badge-dot"></span> Patient
                                        </span>
                                    @endif
                                </td>
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
        @php $isPatientFilter = $filter === 'patients'; @endphp
        <div class="glass card-lift rounded-4 text-center p-5">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3"
                  style="width:3rem;height:3rem;"><i class="bi bi-people fs-4"></i></span>
            <h2 class="h5 fw-semibold font-display">
                {{ $search ? 'No customers match your search' : ($isPatientFilter ? 'No patients yet' : 'No customers yet') }}
            </h2>
            <p class="text-muted-foreground mb-3">
                {{ $search
                    ? 'Try a different name or phone number.'
                    : ($isPatientFilter
                        ? 'Customers with a prescription on file appear here. Add an eye record to a customer to make them a patient.'
                        : 'Add your first customer to start tracking prescriptions and orders.') }}
            </p>
            @unless ($search || $isPatientFilter)
                <a href="{{ route('tenant.customers.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add your first customer
                </a>
            @endunless
        </div>
    @endif
</div>
@endsection
