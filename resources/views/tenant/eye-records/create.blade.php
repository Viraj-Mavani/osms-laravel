@extends('layouts.app')
@section('title', 'New eye record')

@php
    $fields = [
        ['key' => 'sph', 'label' => 'SPH', 'type' => 'number', 'step' => '0.25', 'hint' => 'e.g. -1.50'],
        ['key' => 'cyl', 'label' => 'CYL', 'type' => 'number', 'step' => '0.25', 'hint' => 'e.g. -0.75'],
        ['key' => 'axis', 'label' => 'Axis', 'type' => 'number', 'step' => '1', 'hint' => '0–180°'],
        ['key' => 'add', 'label' => 'ADD', 'type' => 'number', 'step' => '0.25', 'hint' => 'e.g. +2.00'],
        ['key' => 'va', 'label' => 'VA', 'type' => 'text', 'step' => null, 'hint' => 'e.g. 6/6'],
        ['key' => 'spl', 'label' => 'Spl', 'type' => 'number', 'step' => '0.25', 'hint' => ''],
        ['key' => 'dv', 'label' => 'D.V.', 'type' => 'number', 'step' => '0.25', 'hint' => ''],
        ['key' => 'nv', 'label' => 'N.V.', 'type' => 'number', 'step' => '0.25', 'hint' => ''],
    ];
@endphp

@section('content')
<div class="p-4 p-md-5" style="max-width:56rem;">
    <a href="{{ route('tenant.patients.show', $patient) }}"
       class="d-inline-flex align-items-center gap-1 text-muted-foreground text-decoration-none mb-3" style="font-size:.8rem;">
        <i class="bi bi-chevron-left"></i> Back to {{ $patient->name }}
    </a>

    <p class="section-label mb-1">New eye record</p>
    <h1 class="h3 fw-semibold font-display mb-1">Prescription</h1>
    <p class="text-muted-foreground mb-4" style="font-size:.9rem;">For {{ $patient->name }} · {{ $patient->phone }}</p>

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 small rounded-3">
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('tenant.eye-records.store', $patient) }}" class="d-flex flex-column gap-4">
                @csrf

                <div class="row g-4">
                    @foreach (['od' => 'OD (Right)', 'os' => 'OS (Left)'] as $eye => $eyeLabel)
                        <div class="col-md-6">
                            <h3 class="h6 fw-semibold font-display mb-3">{{ $eyeLabel }}</h3>
                            <div class="d-flex flex-column gap-2">
                                @foreach ($fields as $f)
                                    <div class="row align-items-center g-2">
                                        <label class="col-3 col-form-label small fw-medium text-muted-foreground"
                                               for="{{ $eye }}_{{ $f['key'] }}">{{ $f['label'] }}</label>
                                        <div class="col-9">
                                            <input id="{{ $eye }}_{{ $f['key'] }}" name="{{ $eye }}_{{ $f['key'] }}"
                                                   type="{{ $f['type'] }}"
                                                   @if($f['step']) step="{{ $f['step'] }}" @endif
                                                   @if($f['key'] === 'axis') min="0" max="180" @endif
                                                   value="{{ old($eye.'_'.$f['key']) }}"
                                                   class="form-control form-control-sm @error($eye.'_'.$f['key']) is-invalid @enderror"
                                                   placeholder="{{ $f['hint'] }}">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <hr class="my-1">

                <div class="row g-3">
                    <div class="col-sm-4">
                        <label for="pd" class="form-label small fw-medium mb-1">PD (mm)</label>
                        <input id="pd" name="pd" type="number" min="0" max="100" step="0.5"
                               value="{{ old('pd') }}" class="form-control @error('pd') is-invalid @enderror" placeholder="62">
                    </div>
                </div>

                <div>
                    <label for="notes" class="form-label small fw-medium mb-1">Notes</label>
                    <textarea id="notes" name="notes" rows="3" class="form-control"
                              placeholder="Any optometrist remarks…">{{ old('notes') }}</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="{{ route('tenant.patients.show', $patient) }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save eye record</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
