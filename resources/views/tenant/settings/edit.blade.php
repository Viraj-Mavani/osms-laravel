@extends('layouts.app')
@section('title', 'Store settings')

@section('content')
<div class="p-4 p-md-5" style="max-width:48rem;">
    {{-- Header --}}
    <div class="mb-4">
        <p class="section-label mb-1">Workspace</p>
        <h1 class="h3 fw-semibold font-display mb-2">Store settings</h1>
        <p class="text-muted-foreground mb-0" style="font-size:.9rem;">
            These details appear on printed receipts and across your workspace.
        </p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show py-3 px-4 rounded-3 mb-4" role="alert">
            <div class="fw-medium mb-2"><i class="bi bi-exclamation-circle me-2"></i>Please fix the following:</div>
            <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li class="small">{{ $e }}</li>@endforeach</ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form method="POST" action="{{ route('tenant.settings.update') }}" enctype="multipart/form-data"
          class="d-flex flex-column gap-4">
        @csrf
        @method('PUT')

        {{-- Store identity --}}
        <div class="card card-lift border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <p class="section-label mb-3">Store identity</p>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="store_name" class="form-label small fw-medium mb-2">Store name *</label>
                        <input id="store_name" type="text" name="store_name"
                               value="{{ old('store_name', $tenant->store_name) }}"
                               class="form-control @error('store_name') is-invalid @enderror"
                               required placeholder="Sahaj Optical">
                        @error('store_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="tax_id" class="form-label small fw-medium mb-2">GST / Tax ID</label>
                        <input id="tax_id" type="text" name="tax_id" value="{{ old('tax_id', $tenant->tax_id) }}"
                               class="form-control @error('tax_id') is-invalid @enderror" placeholder="22AAAAA0000A1Z5">
                        @error('tax_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-sm-6">
                        <label for="address" class="form-label small fw-medium mb-2">Address</label>
                        <input id="address" type="text" name="address" value="{{ old('address', $tenant->address) }}"
                               class="form-control @error('address') is-invalid @enderror" placeholder="123 Main Street, Mumbai">
                        @error('address')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Logo --}}
        <div class="card card-lift border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <p class="section-label mb-3">Store logo</p>
                <div class="d-flex align-items-center gap-4 flex-wrap">
                    <div class="flex-shrink-0">
                        @if ($tenant->logo_url)
                            <img id="logoPreview" src="{{ $tenant->logo_url }}" alt="{{ $tenant->store_name }}"
                                 class="rounded-4 object-fit-cover border" style="width:5rem;height:5rem;">
                        @else
                            <span id="logoPreview"
                                  class="d-inline-flex align-items-center justify-content-center bg-primary text-white rounded-4"
                                  style="width:5rem;height:5rem;font-size:1.75rem;">
                                <i class="bi bi-shop"></i>
                            </span>
                        @endif
                    </div>
                    <div class="flex-grow-1" style="min-width:14rem;">
                        <label for="logo" class="form-label small fw-medium mb-2">Replace logo</label>
                        <input id="logo" type="file" name="logo" accept="image/*"
                               class="form-control @error('logo') is-invalid @enderror">
                        @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text" style="font-size:.75rem;">
                            Used on printed receipts. PNG or JPG, ideally square. Max 2MB.
                        </div>
                        @if ($tenant->logo_url)
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label small text-muted-foreground" for="remove_logo">
                                    Remove current logo
                                </label>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('tenant.dashboard') }}" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-lg me-2"></i>Save settings
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // Live preview of a newly chosen logo (micro-feedback, no upload yet).
    (function () {
        function init() {
            const input = document.getElementById('logo');
            const preview = document.getElementById('logoPreview');
            if (!input || !preview) return;

            input.addEventListener('change', () => {
                const file = input.files && input.files[0];
                if (!file) return;
                const url = URL.createObjectURL(file);
                if (preview.tagName === 'IMG') {
                    preview.src = url;
                } else {
                    const img = document.createElement('img');
                    img.id = 'logoPreview';
                    img.src = url;
                    img.alt = 'Logo preview';
                    img.className = 'rounded-4 object-fit-cover border';
                    img.style.width = '5rem';
                    img.style.height = '5rem';
                    preview.replaceWith(img);
                }
                const remove = document.getElementById('remove_logo');
                if (remove) remove.checked = false;
            });
        }
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', init);
        } else {
            init();
        }
    })();
</script>
@endpush
