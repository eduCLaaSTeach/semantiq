<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'microsoft' => [

        /*
         * Microsoft Entra ID application registration.
         *
         * CLaaS SemantiQ holds no credential of its own for a federated person:
         * identity is delegated entirely to Entra. All four values are set on
         * the server and never in this repository.
         *
         * 'tenant' is the directory sign-in is issued against. Use a tenant GUID
         * or a verified domain to pin sign-in to one organisation. 'common'
         * accepts any Microsoft work, school or personal account, which is
         * almost never what a control plane wants, so it is not defaulted.
         */
        'tenant' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
    ],

];
