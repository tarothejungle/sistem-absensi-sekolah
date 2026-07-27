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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'hostname' => env('TURNSTILE_HOSTNAME'),
    ],

    'face' => [
        'url' => env('FACE_SERVICE_URL', 'http://fastapi:8001'),
        'api_key' => env('FACE_SERVICE_API_KEY'),
        'connect_timeout' => env('FACE_SERVICE_CONNECT_TIMEOUT', 3),
        'timeout' => env('FACE_SERVICE_TIMEOUT', 15),
    ],

    'attendance' => [
        'face_photo_retention_days' => env('ATTENDANCE_FACE_PHOTO_RETENTION_DAYS', 30),
    ],

];
