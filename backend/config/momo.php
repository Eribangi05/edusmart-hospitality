<?php

return [
    // fake (dev/test) | mtn (MTN MoMo Open API; sandbox or production via MOMO_BASE_URL)
    'driver' => env('MOMO_DRIVER', 'fake'),
    'base_url' => env('MOMO_BASE_URL', 'https://sandbox.momodeveloper.mtn.com'),
    'target_environment' => env('MOMO_TARGET_ENV', 'sandbox'),
    'subscription_key' => env('MOMO_SUBSCRIPTION_KEY'),
    'api_user' => env('MOMO_API_USER'),
    'api_key' => env('MOMO_API_KEY'),
    'currency' => env('MOMO_CURRENCY', 'EUR'), // MTN sandbox only supports EUR; production uses RWF
    'callback_url' => env('MOMO_CALLBACK_URL'),
    'webhook_token' => env('MOMO_WEBHOOK_TOKEN'),
];
