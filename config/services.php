<?php

/**
 * Third Party Services Configuration
 *
 * This file is used to configure third-party services and their credentials.
 * We store the Discord Webhook URLs here to maintain a centralized configuration
 * structure, preventing hardcoded values across the application.
 */
return [

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

    'discord' => [
        'tickets_webhook_url' => env('DISCORD_TICKETS_WEBHOOK_URL'),
        'errors_webhook_url' => env('DISCORD_ERRORS_WEBHOOK_URL'),
    ],

];