<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Request $request, BillingService $billing): View
    {
        $subscription = Subscription::first();
        $plans = config('billing.plans');

        return view('tenant.billing.index', [
            'subscription' => $subscription,
            'plans' => $plans,
            'configured' => $billing->isConfigured(),
        ]);
    }

    public function subscribe(Request $request, BillingService $billing): RedirectResponse|View
    {
        $validated = $request->validate([
            'tier' => ['required', 'in:basic,pro,enterprise'],
        ]);

        if (! $billing->isConfigured()) {
            return back()->with('error', 'Online payments are not configured yet. Please contact support.');
        }

        // Don't create a second Razorpay subscription on top of an active one —
        // it would keep billing while the app only tracks the newest id.
        $existing = Subscription::first();
        if ($existing && $existing->isActive()) {
            return back()->with('error', 'You already have an active subscription. Manage it from billing.');
        }

        $tenant = $request->user()->tenant;

        try {
            $result = $billing->createSubscription($tenant, $validated['tier']);
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not start checkout: ' . $e->getMessage());
        }

        // Persist the pending subscription reference.
        Subscription::updateOrCreate(
            ['tenant_id' => $tenant->id],
            [
                'razorpay_subscription_id' => $result['subscription_id'],
                'tier' => $validated['tier'],
                'status' => 'trialing',
            ],
        );

        // Render the Razorpay checkout page.
        return view('tenant.billing.checkout', [
            'subscriptionId' => $result['subscription_id'],
            'razorpayKey' => $billing->publicKey(),
            'tier' => $validated['tier'],
            'user' => $request->user(),
        ]);
    }

    /** Razorpay redirects/callbacks here after a successful checkout. */
    public function success(Request $request): RedirectResponse
    {
        // The authoritative state change happens via the webhook; this just
        // gives the user friendly feedback.
        return redirect()->route('tenant.billing.index')
            ->with('status', 'Payment received — your subscription will activate momentarily.');
    }
}
