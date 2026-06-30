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

    @if ($errors->any())
        <div class="alert alert-danger py-2 px-3 small rounded-3">{{ $errors->first() }}</div>
    @endif

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('tenant.patients.store') }}" class="d-flex flex-column gap-3">
                @csrf
                <div class="row g-3">
                    <div class="col-sm-6">
                        <label for="name" class="form-label small fw-medium mb-1">Full name *</label>
                        <input id="name" name="name" type="text" value="{{ old('name') }}"
                               class="form-control @error('name') is-invalid @enderror"
                               required autofocus placeholder="Rahul Kumar">
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="phone" class="form-label small fw-medium mb-1">Phone *</label>
                        @php
                            $codes = ['+91', '+1', '+44', '+971', '+61', '+65', '+880', '+977'];
                            $oldCode = old('country_code', '+91');
                            // old('phone') is the normalised "{code} {national}" — show only the national part.
                            $oldNational = \Illuminate\Support\Str::of(old('phone', ''))->afterLast(' ')->toString();
                        @endphp
                        <div class="input-group">
                            <select name="country_code" class="form-select flex-grow-0 w-auto" aria-label="Country code">
                                @foreach ($codes as $code)
                                    <option value="{{ $code }}" @selected($oldCode === $code)>{{ $code }}</option>
                                @endforeach
                            </select>
                            <input id="phone" name="phone" type="tel" value="{{ $oldNational }}"
                                   class="form-control @error('phone') is-invalid @enderror"
                                   required placeholder="98765 43210">
                            @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <label for="age" class="form-label small fw-medium mb-1">Age</label>
                        <input id="age" name="age" type="number" min="0" max="150" value="{{ old('age') }}"
                               class="form-control @error('age') is-invalid @enderror" placeholder="32">
                        @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="gender" class="form-label small fw-medium mb-1">Gender</label>
                        <select id="gender" name="gender" class="form-select">
                            <option value="">Prefer not to say</option>
                            <option value="male" @selected(old('gender') === 'male')>Male</option>
                            <option value="female" @selected(old('gender') === 'female')>Female</option>
                            <option value="other" @selected(old('gender') === 'other')>Other</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <a href="{{ route('tenant.patients.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save patient</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
