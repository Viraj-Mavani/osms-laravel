@extends('layouts.app')
@section('title', 'New patient')

@section('content')
<div class="p-4 p-md-5" style="max-width:48rem;">
    <a href="{{ route('tenant.patients.index') }}"
       class="d-inline-flex align-items-center gap-1 text-muted-foreground text-decoration-none mb-3" style="font-size:.8rem;">
        <i class="bi bi-chevron-left"></i> Back to patients
    </a>

    <p class="section-label mb-1">New patient</p>
    <h1 class="h3 fw-semibold font-display mb-4">Add patient</h1>

    @include('tenant.patients._form', ['patient' => null])
</div>
@endsection
