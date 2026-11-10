<?php

return [
    'registration_enabled' => false,
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),
    'max_recipients' => 1000,
    'queue_warning_seconds' => (int) env('SMS_QUEUE_WARNING_SECONDS', 300),
    'daily_recipient_limit' => 5000,
    'sms_provider' => env('SMS_PROVIDER', 'semaphore'),
    'semaphore' => [
        'dry_run' => env('SEMAPHORE_DRY_RUN', true),
        'api_key' => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME'),
    ],
    'itexmo' => [
        'dry_run' => env('ITEXMO_DRY_RUN', true),
        'email' => env('ITEXMO_EMAIL'),
        'api_code' => env('ITEXMO_API_CODE'),
        'password' => env('ITEXMO_PASSWORD'),
    ],
];
