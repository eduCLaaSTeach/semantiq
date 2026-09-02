<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Organisation\Support\SystemAdministratorNavigationAuthorizer;
use App\Modules\Platform\Console\Commands\IssueBootstrapGrantCommand;
use App\Modules\Platform\Console\HealthCommand;
use App\Modules\Platform\Console\SessionPolicyCommand;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Identity\Microsoft\EntraProvider;
use App\Modules\Platform\Identity\Microsoft\IdTokenValidator;
use App\Shared\Navigation\Contracts\NavigationAuthorizer;
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
        /*
         * P1-01 replaces DenyAllNavigationAuthorizer: there is now something to
         * navigate to. Still UX only - every route re-authorises on its own.
         */
        $this->app->bind(NavigationAuthorizer::class, SystemAdministratorNavigationAuthorizer::class);

        $this->app->singleton(NavigationRegistry::class, fn ($app): NavigationRegistry => new NavigationRegistry(
            $app->make(NavigationAuthorizer::class),
            $app->make(Registrar::class),
        ));

        $this->registerIdentity();
    }

    /**
     * One provider, bound to the boundary interface.
     *
     * The interface exists so a later approved provider can be added without
     * changing the application's authentication contract - not so a generic
     * identity framework grows here. D-13 is explicit about that scope.
     */
    private function registerIdentity(): void
    {
        $this->app->singleton(EntraDiscovery::class, fn (): EntraDiscovery => new EntraDiscovery(
            (string) config('identity.microsoft.tenant_id'),
        ));

        $this->app->singleton(IdTokenValidator::class, fn ($app): IdTokenValidator => new IdTokenValidator(
            $app->make(EntraDiscovery::class),
            (string) config('identity.microsoft.client_id'),
            (string) config('identity.microsoft.tenant_id'),
        ));

        $this->app->singleton(IdentityProvider::class, fn ($app): IdentityProvider => new EntraProvider(
            $app->make(EntraDiscovery::class),
            $app->make(IdTokenValidator::class),
            (string) config('identity.microsoft.tenant_id'),
            (string) config('identity.microsoft.client_id'),
            (string) config('identity.microsoft.client_secret'),
            (string) config('identity.microsoft.redirect_uri'),
        ));

        /*
         * P1-02 needs the set of identity providers to be ENUMERABLE, not just
         * resolvable. Without a tag the container can hand back the one binding
         * and nothing can ask "what else is there?" - so a second provider added
         * later would be invisible to the guard that exists to catch it.
         *
         * The tag is what makes ProviderInventory possible, and ApprovedProviders
         * is what decides whether anything found there may sign people in. The
         * two are deliberately different questions.
         */
        $this->app->tag([IdentityProvider::class], 'identity.providers');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([HealthCommand::class, IssueBootstrapGrantCommand::class, SessionPolicyCommand::class]);
        }

        // The Platform module still registers NO navigation nodes. P1-01
        // registers Organisation in its own provider and P1-02 registers
        // Identity & SSO in its own - a module owns its nodes.
    }
}
