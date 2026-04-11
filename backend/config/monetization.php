<?php

return [
    'default_access_hours' => env('DEFAULT_ACCESS_HOURS', 24),
    'max_commission_percentage' => env('MAX_COMMISSION_PERCENTAGE', 30),
    'default_commission_type' => 'percentage',
    'default_commission_value' => env('DEFAULT_COMMISSION_VALUE', 10),
    'minimum_wallet_topup_rupees' => env('MINIMUM_WALLET_TOPUP_RUPEES', 100),
    'minimum_withdrawal_amount' => env('MINIMUM_WITHDRAWAL_AMOUNT', 500),
    'verification_code_ttl_minutes' => (int) env('VERIFICATION_CODE_TTL_MINUTES', 10),
];
