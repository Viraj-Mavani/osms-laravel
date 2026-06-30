@extends('layouts.app')
@section('title', 'Edit ' . $patient->name)

@section('content')
<div class="p-4 p-md-5" style="max-width:48rem;">
    <a href="{{ route('tenant.patients.show', $patient) }}"
       class="d-inline-flex align-items-center gap-1 text-muted-foreground text-decoration-none mb-3" style="font-size:.8rem;">
        <i class="bi bi-chevron-left"></i> Back to patient
    </a>

    <p class="section-label mb-1">Edit patient</p>
    <h1 class="h3 fw-semibold font-display mb-4">{{ $patient->name }}</h1>

    @include('tenant.patients._form', ['patient' => $patient])
</div>
@endsection
