<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SumUp API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for SumUp payment integration.
    |
    */

    'api_key' => env('SUMUP_API_KEY'),
    'public_key' => env('SUMUP_PUBLIC_KEY'),
    'merchant_code' => env('SUMUP_MERCHANT_CODE'),
    'affiliate_key' => env('SUMUP_AFFILIATE_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live payments.
    |
    */

    'environment' => env('SUMUP_ENV', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | SumUp API endpoint. Same for sandbox and production, mode is determined
    | by the API key prefix (sk_test_ vs sk_live_).
    |
    */

    'api_url' => 'https://api.sumup.com',

    /*
    |--------------------------------------------------------------------------
    | Checkout Settings
    |--------------------------------------------------------------------------
    */

    'checkout' => [
        'currency' => env('PHOTO_CURRENCY', 'EUR'),
        'locale' => 'fr-FR',
        'return_url' => env('FRONTEND_URL', 'http://localhost:5173') . '/commande/confirmation',
    ],

    /*
    |--------------------------------------------------------------------------
    | Photo Pricing
    |--------------------------------------------------------------------------
    */

    'photo' => [
        'default_price' => (float) env('PHOTO_DEFAULT_PRICE', 5.00),
        'currency' => env('PHOTO_CURRENCY', 'EUR'),
    ],
];
