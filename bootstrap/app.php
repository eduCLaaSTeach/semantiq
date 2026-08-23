<?php

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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
