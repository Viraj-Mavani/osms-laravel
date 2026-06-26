<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subscription Plans (tiers)
    |--------------------------------------------------------------------------
    | Display metadata for each tier. Prices are in INR/month. The actual
    | Razorpay plan_id used to create the subscription comes from
    | config('services.razorpay.plans.<tier>').
    */

    'trial_days' => 14,

    'plans' => [
        'basic' => [
            'name' => 'Basic',
            'price' => 499,
            'features' => [
                'Up to 2 staff users',
                'Patients & prescriptions',
                'Inventory + barcode POS',
                'Order kanban & receipts',
            ],
        ],
        'pro' => [
            'name' => 'Pro',
            'price' => 999,
            'popular' => true,
            'features' => [
                'Up to 8 staff users',
                'Everything in Basic',
                'Analytics & profit reports',
                'Excel export',
                'Priority support',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'price' => 2499,
            'features' => [
                'Unlimited staff users',
                'Everything in Pro',
                'Multi-branch ready',
                'Dedicated onboarding',
                'SMS / WhatsApp add-ons',
            ],
        ],
    ],
];
