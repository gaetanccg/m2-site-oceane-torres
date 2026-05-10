<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sms_sender' => env('BREVO_SMS_SENDER', 'OceanePhoto'),
    ],

];
