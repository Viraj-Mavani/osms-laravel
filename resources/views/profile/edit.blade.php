@extends('layouts.app')
@section('title', 'Account settings')

@section('content')
<div class="p-4 p-md-5" style="max-width: 720px;">
    <div class="mb-4">
        <p class="section-label mb-1">Account</p>
        <h1 class="h3 fw-semibold font-display mb-0">Settings</h1>
    </div>

    @if (session('status') === 'profile-updated')
        <div class="alert alert-success py-2 px-3 small rounded-3">Profile updated.</div>
    @endif

    {{-- Profile information --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Profile information</h2>
            <form method="POST" action="{{ route('profile.update') }}" class="d-flex flex-column gap-3">
                @csrf
                @method('patch')
                <div>
                    <label for="name" class="form-label small fw-medium mb-1">Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}"
                           class="form-control @error('name') is-invalid @enderror" required autocomplete="name">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="email" class="form-label small fw-medium mb-1">Email</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}"
                           class="form-control @error('email') is-invalid @enderror" required autocomplete="email">
                    @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div><button type="submit" class="btn btn-primary fw-medium">Save</button></div>
            </form>
        </div>
    </div>

    {{-- Update password --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-3">Update password</h2>
            <form method="POST" action="{{ route('password.update') }}" class="d-flex flex-column gap-3">
                @csrf
                @method('put')
                <div>
                    <label for="current_password" class="form-label small fw-medium mb-1">Current password</label>
                    <input id="current_password" name="current_password" type="password"
                           class="form-control @error('current_password', 'updatePassword') is-invalid @enderror" autocomplete="current-password">
                    @error('current_password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="password" class="form-label small fw-medium mb-1">New password</label>
                    <input id="password" name="password" type="password"
                           class="form-control @error('password', 'updatePassword') is-invalid @enderror" autocomplete="new-password">
                    @error('password', 'updatePassword')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="form-label small fw-medium mb-1">Confirm password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password"
                           class="form-control" autocomplete="new-password">
                </div>
                <div><button type="submit" class="btn btn-primary fw-medium">Update password</button></div>
            </form>
        </div>
    </div>

    {{-- Delete account --}}
    <div class="card border-0 shadow-sm rounded-4 border-danger-subtle">
        <div class="card-body p-4">
            <h2 class="h6 fw-semibold mb-1 text-danger">Delete account</h2>
            <p class="text-muted-foreground small mb-3">
                Once deleted, all of its resources and data are permanently removed.
            </p>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                Delete account
            </button>
        </div>
    </div>
</div>

{{-- Delete confirmation modal --}}
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" action="{{ route('profile.destroy') }}" class="modal-content rounded-4 border-0">
            @csrf
            @method('delete')
            <div class="modal-header border-0">
                <h5 class="modal-title">Delete account?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted-foreground small">Enter your password to confirm permanent deletion.</p>
                <input type="password" name="password" class="form-control @error('password', 'userDeletion') is-invalid @enderror"
                       placeholder="Password" required>
                @error('password', 'userDeletion')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Delete account</button>
            </div>
        </form>
    </div>
</div>
@endsection
