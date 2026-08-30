<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Platform\Console\HealthCommand;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;
use App\Shared\Navigation\DenyAllNavigationAuthorizer;
use App\Shared\Navigation\NavigationRegistry;
use Illuminate\Contracts\Routing\Registrar;
use Illuminate\Support\ServiceProvider;

/**
 * The Platform module: shell, health and configuration.
 *
 * The only module in P1-BASE. Adding a module means adding a directory and a
 * provider, not editing a central list - so Identity, Organisation, Access and
 * Audit arrive with their own units rather than being reserved here as empty
 * directories.
 */
final class PlatformServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(NavigationAuthorizer::class, DenyAllNavigationAuthorizer::class);

        $this->app->singleton(NavigationRegistry::class, fn ($app): NavigationRegistry => new NavigationRegistry(
            $app->make(NavigationAuthorizer::class),
            $app->make(Registrar::class),
        ));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([HealthCommand::class]);
        }

        // P1-BASE registers NO navigation nodes. There is nothing implemented to
        // navigate to, and a node pointing at nothing is exactly the placeholder
        // the design forbids. Later units register their own here.
    }
}
