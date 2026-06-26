@extends('layouts.app')
@section('title', 'Billing & plans')

@section('content')
<div class="p-4 p-md-5">
    <div class="mb-4">
        <p class="section-label mb-1">Account</p>
        <h1 class="h3 fw-semibold font-display mb-1">Billing &amp; plans</h1>
        <p class="text-muted-foreground mb-0" style="font-size:.9rem;">Manage your OSMS subscription.</p>
    </div>

    @if (session('error'))
        <div class="alert alert-danger py-2 px-3 small rounded-3">{{ session('error') }}</div>
    @endif

    {{-- Current subscription --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-body p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div>
                <p class="section-label mb-1">Current plan</p>
                <div class="d-flex align-items-center gap-2">
                    <span class="h4 fw-semibold font-display mb-0 text-capitalize">
                        {{ $subscription?->tier ?? 'None' }}
                    </span>
                    @php $s = $subscription?->status; @endphp
                    <span class="badge {{ in_array($s, ['active','trialing']) ? 'text-bg-success' : ($s === 'past_due' ? 'text-bg-warning' : 'text-bg-secondary') }}">
                        {{ $s ?? 'inactive' }}
                    </span>
                </div>
                @if ($subscription?->current_period_end)
                    <p class="text-muted-foreground mb-0 mt-1" style="font-size:.82rem;">
                        {{ $subscription->status === 'trialing' ? 'Trial ends' : 'Renews' }}
                        {{ $subscription->current_period_end->format('d M Y') }}
                    </p>
                @endif
            </div>
        </div>
    </div>

    @unless ($configured)
        <div class="alert alert-info py-2 px-3 small rounded-3">
            <i class="bi bi-info-circle me-1"></i>
            Online payments aren't configured yet. Add your Razorpay keys to <code>.env</code> to enable checkout.
        </div>
    @endunless

    {{-- Plans --}}
    <div class="row g-3">
        @foreach ($plans as $key => $plan)
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 h-100 {{ ($plan['popular'] ?? false) ? 'border-primary' : '' }}"
                     style="{{ ($plan['popular'] ?? false) ? 'outline:2px solid var(--osms-primary);' : '' }}">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h2 class="h5 fw-semibold font-display mb-0">{{ $plan['name'] }}</h2>
                            @if ($plan['popular'] ?? false)<span class="badge text-bg-primary">Popular</span>@endif
                        </div>
                        <p class="mb-3">
                            <span class="h3 fw-semibold font-display">₹{{ number_format($plan['price']) }}</span>
                            <span class="text-muted-foreground">/mo</span>
                        </p>
                        <ul class="list-unstyled d-flex flex-column gap-2 mb-4 flex-grow-1" style="font-size:.88rem;">
                            @foreach ($plan['features'] as $f)
                                <li><i class="bi bi-check-circle-fill text-primary me-2"></i>{{ $f }}</li>
                            @endforeach
                        </ul>
                        <form method="POST" action="{{ route('tenant.billing.subscribe') }}">
                            @csrf
                            <input type="hidden" name="tier" value="{{ $key }}">
                            <button type="submit" class="btn w-100 {{ ($plan['popular'] ?? false) ? 'btn-primary' : 'btn-outline-primary' }}"
                                    {{ $subscription?->tier === $key && $subscription?->status === 'active' ? 'disabled' : '' }}>
                                {{ $subscription?->tier === $key && $subscription?->status === 'active' ? 'Current plan' : 'Choose '.$plan['name'] }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
