@extends('layouts.app')
@section('title', 'Edit item')

@section('content')
<div class="p-4 p-md-5" style="max-width:54rem;">
    <a href="{{ route('tenant.inventory.index') }}"
       class="d-inline-flex align-items-center gap-1 text-muted-foreground text-decoration-none mb-3" style="font-size:.8rem;">
        <i class="bi bi-chevron-left"></i> Back to inventory
    </a>
    <p class="section-label mb-1">Edit item</p>
    <h1 class="h3 fw-semibold font-display mb-1">{{ $item->brand }} {{ $item->model_name }}</h1>
    <p class="text-muted-foreground mb-4 font-monospace" style="font-size:.82rem;">{{ $item->sku }}</p>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            @include('tenant.inventory._form', ['mode' => 'edit', 'item' => $item])
        </div>
    </div>
</div>
@endsection
