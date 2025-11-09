<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    |
    | This option controls the default payment gateway that will be used
    | for processing payments. You can override this for specific transactions.
    |
    | Supported: "fawry", "paymob", "paytabs"
    |
    */

    'default_gateway' => env('PAYMENT_GATEWAY', 'fawry'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    |
    | The default currency for all transactions
    |
    */

    'currency' => env('PAYMENT_CURRENCY', 'EGP'),

    /*
    |--------------------------------------------------------------------------
    | Flutter App Deep Link Scheme
    |--------------------------------------------------------------------------
    |
    | Used for redirecting users back to the mobile app after payment
    |
    */

    'flutter_scheme' => env('FLUTTER_SCHEME', 'lmsapp'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways Configuration
    |--------------------------------------------------------------------------
    */

    'gateways' => [
        'fawry' => [
            'enabled' => env('FAWRY_ENABLED', true),
            'merchant_id' => env('FAWRY_MERCHANT_ID'),
            'secret' => env('FAWRY_SECRET'),
            'base_url' => env('FAWRY_SANDBOX', true)
                ? 'https://atfawry.fawrystaging.com'
                : 'https://www.atfawry.com',
            'callback_url' => env('APP_URL') . '/api/v1/payments/fawry/callback',
            'return_url' => env('APP_URL') . '/api/v1/payments/fawry/return',
        ],

        'paymob' => [
            'enabled' => env('PAYMOB_ENABLED', true),
            'api_key' => env('PAYMOB_API_KEY'),
            'integration_id' => env('PAYMOB_INTEGRATION_ID'),
            'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
            'base_url' => env('PAYMOB_SANDBOX', true)
                ? 'https://accept.paymobsolutions.com'
                : 'https://accept.paymob.com',
            'iframe_url' => env('PAYMOB_IFRAME_URL', 'https://accept.paymob.com/api/acceptance/iframes/'),
            'callback_url' => env('APP_URL') . '/api/v1/payments/paymob/callback',
            'return_url' => env('APP_URL') . '/api/v1/payments/paymob/return',
        ],

        'paytabs' => [
            'enabled' => env('PAYTABS_ENABLED', true),
            'profile_id' => env('PAYTABS_PROFILE_ID'),
            'server_key' => env('PAYTABS_SERVER_KEY'),
            'base_url' => env('PAYTABS_REGION', 'egypt') === 'egypt'
                ? 'https://secure-egypt.paytabs.com'
                : 'https://secure.paytabs.com',
            'callback_url' => env('APP_URL') . '/api/v1/payments/paytabs/callback',
            'return_url' => env('APP_URL') . '/api/v1/payments/paytabs/return',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Verification Settings
    |--------------------------------------------------------------------------
    */

    'verification' => [
        'max_attempts' => 3,
        'retry_delay' => 5, // seconds
        'timeout' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Logging
    |--------------------------------------------------------------------------
    */

    'logging' => [
        'enabled' => env('PAYMENT_LOGGING_ENABLED', true),
        'channel' => env('PAYMENT_LOG_CHANNEL', 'daily'),
    ],
];
