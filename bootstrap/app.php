<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Platform\Providers\PlatformServiceProvider;
use App\Modules\Platform\Support\DeploymentLayout;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // Registered with no middleware group at all - see routes/health.php.
            Route::group([], __DIR__.'/../routes/health.php');
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

/*
 * Where the servable directory is depends on the layout, and this file is the
 * only place both entry points agree on it. index.php and artisan each load
 * bootstrap/app.php, so setting it here means HTTP requests, Artisan commands,
 * semantiq:health and Vite manifest lookup all resolve public_path() the same
 * way. Setting it in the front controller would fix the web and silently leave
 * every CLI path pointing at a directory that does not exist in production.
 *
 * See DeploymentLayout for why the layout is derived rather than configured.
 */
$app->usePublicPath(DeploymentLayout::publicPath($app->basePath()));

return $app;
