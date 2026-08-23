<?php

use App\Http\Middleware\EnforceNavigationPolicy;
use App\Http\Middleware\EnforcePermission;
use App\Modules\Platform\Http\Middleware\AssignCorrelationId;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Laravel's defaults point at routes named "login" and "home", and this
         * application has neither name. Without these, the auth middleware
         * throws a route-not-found error instead of redirecting, turning every
         * unauthenticated visit to a protected page into a 500.
         */
        $middleware->redirectGuestsTo(fn () => route('sign-in'));
        $middleware->redirectUsersTo('/');

        /*
         * Gates a route by the same policy that gates its sidebar entry. A
         * filtered sidebar hides a link and does nothing about a typed URL.
         */
        $middleware->alias([
            'policy' => EnforceNavigationPolicy::class,
            /*
             * The finer gate, added in Release 1 gate 2. `policy` checks the
             * tier and the cluster; `permission` checks a specific declared
             * permission. Routes that need both name both.
             */
            'permission' => EnforcePermission::class,
        ]);

        /*
         * Runs first, so anything that logs, audits or fails later in the
         * request already has an id to quote. ADM-024 asks Diagnostics to show
         * recent error correlation ids; this is where they come from.
         */
        $middleware->web(prepend: [
            AssignCorrelationId::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
