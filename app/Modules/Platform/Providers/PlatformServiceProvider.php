<?php

declare(strict_types=1);

namespace App\Modules\Platform\Providers;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Access\StepUp\StepUpCompletionRegistry;
use App\Modules\Organisation\Support\SystemAdministratorNavigationAuthorizer;
use App\Modules\Platform\Console\Commands\IssueBootstrapGrantCommand;
use App\Modules\Platform\Console\HealthCommand;
use App\Modules\Platform\Console\SessionPolicyCommand;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\Microsoft\EntraDiscovery;
use App\Modules\Platform\Identity\Microsoft\EntraProvider;
use App\Modules\Platform\Identity\Microsoft\IdTokenValidator;
use App\Modules\Platform\Setup\Connections\SendsTestEmail;
use App\Modules\Platform\Setup\Connections\TestEmailSender;
use App\Modules\Platform\Setup\Console\CreateBootstrapAdministratorCommand;
use App\Modules\Platform\Setup\Console\IssueBootstrapRecoveryCommand;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\StepUp\IntegrationSecretStepUpCompletion;
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
         * D-153's send, behind its declared seam.
         *
         * TestEmailSender stays final - it is the one place that opens an SMTP
         * connection and decides where a message goes - so the substitutable
         * thing is the interface rather than the class.
         */
        $this->app->bind(SendsTestEmail::class, TestEmailSender::class);

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
        $this->registerAccess();
    }

    /**
     * P1-05.
     *
     * AccessEngine is a SINGLETON because there must be exactly one of it -
     * binding it as a singleton is not a performance choice, it is the shape
     * that makes "one engine" true of the running application as well as of the
     * source. Everything else resolves normally.
     *
     * There is deliberately no AccessServiceProvider: People and Domains have
     * none either, and a provider per module would be a central list by
     * another name.
     */
    private function registerAccess(): void
    {
        $this->app->singleton(AccessEngine::class);
        $this->app->singleton(AdministratorSetGuard::class);
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
        /*
         * ONE CONFIGURATION SOURCE PER REQUEST.
         *
         * singleton() here and bind() below is deliberate and is not a
         * contradiction. The SOURCE is memoised so one request runs the lookup
         * once however many providers it builds; the PROVIDERS are per
         * resolution so none of them outlives the configuration it was built
         * from. Swap the two and either every binding re-queries, or a stale
         * provider survives a configuration change.
         */
        $this->app->singleton(IdentityConfigurationSource::class);

        /*
         * BIND, NOT SINGLETON, AND RESOLVE THE CONFIGURATION INSIDE THE
         * CLOSURE.
         *
         * These three were singletons that read config() once, so the tenant
         * was BAKED IN at first resolution and EntraDiscovery's cache keys were
         * namespaced by that baked-in value. The consequence is exactly the
         * path this unit exists to serve: an administrator changes the tenant
         * in First-Run and presses Test in the SAME REQUEST. If anything had
         * already resolved the provider - a middleware, a health row, a shared
         * prop - the test validated the PREVIOUS configuration and reported
         * success. A guarantee that holds in the steady state and fails on the
         * one path the feature was built for.
         *
         * bind() gives a fresh instance per resolution, and the configuration
         * is read per resolution too, so no instance can outlive the
         * configuration it was built from. The cost is constructing three small
         * objects per resolution; the alternative is a test that lies.
         *
         * THE TEST PATH STILL DOES NOT COME THROUGH HERE. ProviderProbe builds
         * its own provider from the CANDIDATE configuration with its own cache
         * namespace, so a probe can neither read nor poison live trust.
         */
        $this->app->bind(EntraDiscovery::class, fn ($app): EntraDiscovery => new EntraDiscovery(
            $app->make(IdentityConfigurationSource::class)->resolve()->tenantId,
        ));

        $this->app->bind(IdTokenValidator::class, function ($app): IdTokenValidator {
            $identity = $app->make(IdentityConfigurationSource::class)->resolve();

            return new IdTokenValidator(
                $app->make(EntraDiscovery::class),
                $identity->clientId,
                $identity->tenantId,
            );
        });

        $this->app->bind(IdentityProvider::class, function ($app): IdentityProvider {
            $identity = $app->make(IdentityConfigurationSource::class)->resolve();

            return new EntraProvider(
                $app->make(EntraDiscovery::class),
                $app->make(IdTokenValidator::class),
                $identity->tenantId,
                $identity->clientId,
                $identity->clientSecret,
                $identity->redirectUri,
            );
        });

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
        /*
         * P1-10's half of D-159, registered through the seam P1-07 opened.
         *
         * ONE LINE, HERE, rather than an import inside StepUpController. P1-05
         * must not learn what an integration credential is: later units consume
         * Access, never the other way round, and the moment that reverses the
         * accepted unit becomes a switchboard for everything that came after.
         */
        $this->app->make(StepUpCompletionRegistry::class)
            ->register($this->app->make(IntegrationSecretStepUpCompletion::class));

        if ($this->app->runningInConsole()) {
            $this->commands([
                HealthCommand::class,
                IssueBootstrapGrantCommand::class,
                SessionPolicyCommand::class,
                // P1-10. SSH only, both of them, and deliberately: one creates
                // the pre-SSO credential that can complete First-Run, and the
                // other is the ONLY thing that can reopen it once it closes.
                CreateBootstrapAdministratorCommand::class,
                IssueBootstrapRecoveryCommand::class,
            ]);
        }

        // The Platform module still registers NO navigation nodes. P1-01
        // registers Organisation in its own provider and P1-02 registers
        // Identity & SSO in its own - a module owns its nodes.
    }
}
