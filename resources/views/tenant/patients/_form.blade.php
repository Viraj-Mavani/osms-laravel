@php
    /** @var \App\Models\Patient|null $patient */
    $patient = $patient ?? null;
    $isEdit = (bool) $patient;

    $codes = ['+91', '+1', '+44', '+971', '+61', '+65', '+880', '+977'];

    // Stored phone is normalised as "{code} {national}" (e.g. "+91 9876543210").
    // Split it back for the form; old() (a failed submit) takes precedence.
    $storedPhone = (string) ($patient->phone ?? '');
    $storedCode = str_contains($storedPhone, ' ') ? \Illuminate\Support\Str::before($storedPhone, ' ') : '+91';
    $storedNational = str_contains($storedPhone, ' ') ? \Illuminate\Support\Str::afterLast($storedPhone, ' ') : $storedPhone;

    $oldCode = old('country_code', $storedCode);
    $oldNational = old('phone')
        ? \Illuminate\Support\Str::of(old('phone'))->afterLast(' ')->toString()
        : $storedNational;

    $cancelUrl = $isEdit ? route('tenant.patients.show', $patient) : route('tenant.patients.index');
@endphp

@if ($errors->any())
    <div class="alert alert-danger py-2 px-3 small rounded-3">{{ $errors->first() }}</div>
@endif

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-body p-4">
        <form method="POST"
              action="{{ $isEdit ? route('tenant.patients.update', $patient) : route('tenant.patients.store') }}"
              class="d-flex flex-column gap-3">
            @csrf
            @if ($isEdit) @method('PUT') @endif
            <div class="row g-3">
                <div class="col-sm-6">
                    <label for="name" class="form-label small fw-medium mb-1">Full name *</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $patient->name ?? '') }}"
                           class="form-control @error('name') is-invalid @enderror"
                           required autofocus placeholder="Rahul Kumar">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="phone" class="form-label small fw-medium mb-1">Phone *</label>
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
                    <input id="age" name="age" type="number" min="0" max="150" value="{{ old('age', $patient->age ?? '') }}"
                           class="form-control @error('age') is-invalid @enderror" placeholder="32">
                    @error('age')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-sm-6">
                    <label for="gender" class="form-label small fw-medium mb-1">Gender</label>
                    @php $g = old('gender', $patient->gender ?? ''); @endphp
                    <select id="gender" name="gender" class="form-select">
                        <option value="">Prefer not to say</option>
                        <option value="male" @selected($g === 'male')>Male</option>
                        <option value="female" @selected($g === 'female')>Female</option>
                        <option value="other" @selected($g === 'other')>Other</option>
                    </select>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-2">
                <a href="{{ $cancelUrl }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-lg me-1"></i>{{ $isEdit ? 'Save changes' : 'Save patient' }}
                </button>
            </div>
        </form>
    </div>
</div>
