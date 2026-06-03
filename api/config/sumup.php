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

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | Set to 'sandbox' for testing or 'production' for live payments.
    |
    */

    'environment' => env('SUMUP_ENV', 'production'),

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
        'currency' => 'EUR',
        'locale' => 'fr-FR',

        /*
        | IMPORTANT : SumUp utilise UNE SEULE URL pour DEUX usages distincts
        | (cf. https://developer.sumup.com/online-payments/webhooks) :
        |   - GET → redirection navigateur après 3DS (avec ?checkout_id=…)
        |   - POST → webhook CHECKOUT_STATUS_CHANGED ({event_type, id})
        | L'URL doit donc pointer vers le backend Laravel, surtout pas le SPA
        | statique (qui swallowait silencieusement le POST en renvoyant 200/HTML
        | et laissait les commandes en pending si le navigateur de la cliente
        | n'arrivait pas à compléter le callback côté SDK).
        */
        'return_url' => env('APP_URL', 'http://localhost:8000').'/api/payments/sumup/return',
    ],
];
