<?php

use App\Http\Middleware\EnforceNavigationPolicy;
use App\Http\Middleware\EnforcePermission;
use App\Modules\Governance\Http\Middleware\RequireGovernanceStorage;
use App\Modules\Platform\Http\Middleware\AssignCorrelationId;
use App\Modules\Security\Http\Middleware\ConfirmIdentity;
use App\Modules\Security\Http\Middleware\EnforceSessionPolicy;
use App\Modules\Security\Http\Middleware\LimitRequestSize;
use App\Modules\Security\Http\Middleware\RequireSecurityStorage;
use App\Modules\Security\Http\Middleware\SecurityHeaders;
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
            /*
             * Release 1 gate 3, ADM-010. Demands a recent proof of identity
             * before a critical action. Named AFTER `permission` on every route
             * that uses it, so authorization is settled before anybody is asked
             * to prove themselves again.
             */
            'confirm' => ConfirmIdentity::class,
            /*
             * Release 1 gate 3, ADM-012. Refuses a secret-reference action
             * before its table exists. Runs BEFORE implicit model binding,
             * which is the whole reason it is a middleware - a check inside the
             * controller would arrive after the query that fails.
             */
            'security-storage' => RequireSecurityStorage::class,

            /*
             * Gate 4's equivalent, on the governance WRITE routes only. The
             * read screens explain the state instead of being blocked, because
             * an administrator opening one during a deployment window should be
             * told what is happening. SEC-DEC-072.
             */
            'governance-storage' => RequireGovernanceStorage::class,
        ]);

        /*
         * Runs first, so anything that logs, audits or fails later in the
         * request already has an id to quote. ADM-024 asks Diagnostics to show
         * recent error correlation ids; this is where they come from.
         *
         * `LimitRequestSize` sits immediately behind it, ahead of everything
         * that would parse or store a body: refusing an oversized request after
         * it has been read has already spent the memory the limit exists to
         * protect. Correlation still comes first, so the refusal is traceable.
         */
        $middleware->web(prepend: [
            AssignCorrelationId::class,
            LimitRequestSize::class,
        ]);

        /*
         * Release 1 gate 3.
         *
         * `SecurityHeaders` is appended rather than prepended because it acts
         * on the RESPONSE, and a middleware that decorates a response wants to
         * be as close to the response as possible so that everything below it
         * is covered - including error pages produced further in.
         *
         * `EnforceSessionPolicy` is appended so the framework's own session and
         * authentication middleware have already run: it needs a resolved user
         * and a started session to have anything to judge. ADM-010.
         */
        $middleware->web(append: [
            SecurityHeaders::class,
            EnforceSessionPolicy::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
