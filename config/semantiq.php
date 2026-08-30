<?php

declare(strict_types=1);

return [
    /*
     * Identity provider configuration.
     *
     * Declared here so the shape is fixed and .env.example is complete, but NOT
     * required to boot in P1-BASE - see ConfigurationRequirements. P1-00 moves
     * these keys into the required set when it activates Microsoft
     * authentication. They stay empty until then; a placeholder value would only
     * move the failure from boot to the identity provider.
     */
    'identity' => [
        'microsoft' => [
            'tenant_id' => env('MICROSOFT_TENANT_ID'),
            'client_id' => env('MICROSOFT_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
            'redirect_uri' => env('MICROSOFT_REDIRECT_URI'),
        ],
    ],
];
