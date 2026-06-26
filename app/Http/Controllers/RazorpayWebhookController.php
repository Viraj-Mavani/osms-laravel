<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, BillingService $billing): JsonResponse
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $billing->verifyWebhook($payload, $signature)) {
            return response()->json(['ok' => false], 400);
        }

        $event = $request->input('event');
        $sub = $request->input('payload.subscription.entity', []);
        $razorpayId = $sub['id'] ?? null;

        if (! $razorpayId) {
            return response()->json(['ok' => true]); // nothing actionable
        }

        // Find the subscription without the tenant scope (webhook is unauthenticated).
        $subscription = Subscription::withoutGlobalScopes()
            ->where('razorpay_subscription_id', $razorpayId)
            ->first();

        if (! $subscription) {
            return response()->json(['ok' => true]);
        }

        $status = match ($event) {
            'subscription.activated', 'subscription.charged', 'subscription.resumed' => 'active',
            'subscription.pending', 'subscription.halted' => 'past_due',
            'subscription.cancelled', 'subscription.completed' => 'canceled',
            default => null,
        };

        if ($status) {
            $subscription->status = $status;
        }

        if (! empty($sub['current_end'])) {
            $subscription->current_period_end = Carbon::createFromTimestamp($sub['current_end']);
        }

        $subscription->save();

        return response()->json(['ok' => true]);
    }
}
