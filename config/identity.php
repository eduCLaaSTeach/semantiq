<?php

declare(strict_types=1);

/*
 * Identity provider configuration.
 *
 * P1-BASE declared these keys in config/semantiq.php so the shape was fixed
 * before there was anything to configure. P1-00 activates them, and they move
 * here: one file, one owner, so nobody has to work out which of two config
 * files the provider actually reads.
 *
 * Every value comes from the server environment. The client secret exists only
 * in the server .env - never in this repository, never in CI, never in a page
 * payload, never in a log. SecretAndLeakageTest asserts the page-payload part
 * against real rendered responses rather than trusting it.
 *
 * Required in production, absent elsewhere: CI and developer machines have no
 * Entra tenant, and inventing placeholder values to satisfy a validator would
 * only move the failure from boot, where it is obvious, to the identity
 * provider, where it is not.
 */
return [

    'microsoft' => [
        'tenant_id' => env('MICROSOFT_TENANT_ID', ''),
        'client_id' => env('MICROSOFT_CLIENT_ID', ''),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET', ''),
        'redirect_uri' => env('MICROSOFT_REDIRECT_URI', ''),
    ],

];
