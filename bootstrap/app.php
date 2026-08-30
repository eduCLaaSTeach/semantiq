<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Registered with no middleware group at all - see routes/health.php.
            Illuminate\Support\Facades\Route::group([], __DIR__.'/../routes/health.php');
        },
    )
    ->withProviders([
        PlatformServiceProvider::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Laravel's maintenance mode runs before routing, so /up is NOT exempt
        // by default. Without this, a deploy-time probe would be reporting on
        // maintenance mode rather than on the application - and would report
        // failure for a perfectly healthy release. Exemption is tested.
        $middleware->preventRequestsDuringMaintenance(except: ['up']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
