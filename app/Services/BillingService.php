<?php

namespace App\Services;

use App\Models\Tenant;
use Razorpay\Api\Api;
use RuntimeException;

/**
 * Thin wrapper around the Razorpay SDK. Keeps the controller clean and lets the
 * app run locally without keys (isConfigured() guards live calls).
 */
class BillingService
{
    public function isConfigured(): bool
    {
        return ! empty(config('services.razorpay.key'))
            && ! empty(config('services.razorpay.secret'));
    }

    private function api(): Api
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Razorpay keys are not configured.');
        }

        return new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Create a Razorpay subscription for a tenant on a given tier.
     * Returns ['subscription_id' => ..., 'short_url' => ...].
     */
    public function createSubscription(Tenant $tenant, string $tier): array
    {
        $planId = config("services.razorpay.plans.$tier");
        if (! $planId) {
            throw new RuntimeException("No Razorpay plan configured for tier [$tier].");
        }

        $subscription = $this->api()->subscription->create([
            'plan_id' => $planId,
            'total_count' => 12,        // 12 monthly cycles
            'quantity' => 1,
            'customer_notify' => 1,
            'notes' => ['tenant_id' => $tenant->id, 'tier' => $tier],
        ]);

        return [
            'subscription_id' => $subscription['id'],
            'short_url' => $subscription['short_url'] ?? null,
        ];
    }

    /** Verify a Razorpay webhook signature. */
    public function verifyWebhook(string $payload, string $signature): bool
    {
        $secret = config('services.razorpay.webhook_secret');
        if (! $secret) {
            return false;
        }

        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    public function publicKey(): ?string
    {
        return config('services.razorpay.key');
    }
}
