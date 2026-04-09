<?php

return [
    'credits_per_rupee' => (int) env('CREDITS_PER_RUPEE', 1),
    'min_purchase_credits' => (int) env('MIN_PURCHASE_CREDITS', 50),
    'min_withdrawal_credits' => (int) env('MIN_WITHDRAWAL_CREDITS', 100),
    'default_access_hours' => (int) env('DEFAULT_ARTICLE_ACCESS_HOURS', 24),
    'verification_code_ttl_minutes' => (int) env('VERIFICATION_CODE_TTL_MINUTES', 10),
    'payment_provider' => env('PAYMENT_PROVIDER', 'razorpay'),
];
