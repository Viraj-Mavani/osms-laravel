@extends('layouts.app')
@section('title', 'Patients')

@section('content')
<div class="p-4 p-md-5">
    {{-- Header --}}
    <div class="d-flex flex-column flex-md-row gap-3 align-items-md-end justify-content-between mb-4">
        <div>
            <p class="section-label mb-1">Workspace</p>
            <h1 class="h3 fw-semibold font-display mb-1">Patients</h1>
            <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
                Manage patient profiles, eye records, and order history.
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('tenant.patients.trash') }}" class="btn btn-light">
                <i class="bi bi-archive me-1"></i> Archive
            </a>
            <a href="{{ route('tenant.patients.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> New patient
            </a>
        </div>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('tenant.patients.index') }}" class="mb-4" style="max-width:28rem;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="bi bi-search text-muted-foreground"></i></span>
            <input type="search" name="q" value="{{ $search }}" class="form-control"
                   placeholder="Search by name or phone…" autocomplete="off">
            @if ($search)
                <a href="{{ route('tenant.patients.index') }}" class="btn btn-secondary">Clear</a>
            @endif
        </div>
    </form>

    @if ($patients->isNotEmpty())
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
                        @foreach ($patients as $p)
                            <tr class="search-result-item" role="button"
                                onclick="window.location='{{ route('tenant.patients.show', $p) }}'">
                                <td class="ps-4 fw-medium">{{ $p->name }}</td>
                                <td>{{ $p->phone }}</td>
                                <td>{{ $p->age ?? '—' }}</td>
                                <td class="text-capitalize">{{ $p->gender ?? '—' }}</td>
                                <td class="pe-4 text-muted-foreground" style="font-size:.82rem;">
                                    {{ $p->created_at->format('d M Y') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($patients->hasPages())
            <div class="mt-3">{{ $patients->links() }}</div>
        @endif
    @else
        <div class="glass card-lift rounded-4 text-center p-5">
            <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-primary-subtle text-primary mb-3"
                  style="width:3rem;height:3rem;"><i class="bi bi-people fs-4"></i></span>
            <h2 class="h5 fw-semibold font-display">
                {{ $search ? 'No patients match your search' : 'No patients yet' }}
            </h2>
            <p class="text-muted-foreground mb-3">
                {{ $search ? 'Try a different name or phone number.' : 'Add your first patient to start tracking eye records and orders.' }}
            </p>
            @unless ($search)
                <a href="{{ route('tenant.patients.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i> Add your first patient
                </a>
            @endunless
        </div>
    @endif
</div>
@endsection
