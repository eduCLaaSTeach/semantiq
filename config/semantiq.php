<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Bootstrap System Administrators
    |--------------------------------------------------------------------------
    |
    | Addresses listed here are granted the system_admin role the first time
    | they sign in through Entra ID, which solves the cold-start problem: the
    | first administrator cannot be promoted through the interface, because
    | nobody is yet an administrator to promote them.
    |
    | The address is matched against the verified identity Microsoft returns,
    | never against anything the browser supplies, so listing an address grants
    | nothing unless that account actually authenticates against the configured
    | tenant. The match is case-insensitive.
    |
    | This is a floor, not a ceiling. It never demotes: an account promoted or
    | demoted later through the application keeps whatever role it has, so
    | removing an address from this list does not revoke access. Revoke through
    | the application, or in Entra ID.
    |
    | Set MASTER_ADMIN_EMAILS on the server to override, comma separated.
    |
    */

    'bootstrap_admins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('MASTER_ADMIN_EMAILS', 'salil@lithan.com'))
    ))),

];
