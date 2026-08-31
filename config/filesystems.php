<?php

use App\Modules\Platform\Support\DeploymentLayout;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),

            /*
             * Laravel's built-in /storage/{path} route is disabled.
             *
             * D-08B puts the whole tree inside the document root, so the
             * hardened forwarder must refuse /storage/ to protect logs,
             * sessions and cache on disk. That block lands before Laravel, so
             * the route can never be reached - registering it would leave a
             * URL that resolves in the router and 403s in production.
             *
             * A later unit that needs to serve user files must choose a path
             * outside the protected list and route it through an authorised
             * controller, which under this security model it would need anyway.
             */
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    /*
     * Empty under the production root layout, and that is a safety requirement
     * rather than a preference.
     *
     * There, public_path() IS the deployment root, so the conventional link
     * location public_path('storage') resolves to public_html/storage - the
     * application's real storage directory, holding logs, cache, sessions and
     * compiled views. Running storage:link would try to replace live runtime
     * state with a symlink to a subset of itself. The route that would have
     * served those files is already disabled for the same collision; see
     * RoutePrefixCollisionTest. Any later need to serve user files goes through
     * an authorised controller, which this security model requires anyway.
     *
     * In the repository, CI and local development the layout keeps public/ as a
     * distinct directory, so the conventional link is correct and is declared.
     */
    'links' => DeploymentLayout::allowsPublicStorageLink(base_path())
        ? [public_path('storage') => storage_path('app/public')]
        : [],

];
