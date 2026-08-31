<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Providers;

use App\Shared\Navigation\NavigationNode;
use App\Shared\Navigation\NavigationRegistry;
use App\Shared\Navigation\ProductArea;
use Illuminate\Support\ServiceProvider;

/**
 * The Organisation module: structure, and nothing that decides access.
 *
 * Adding a module means adding a directory and a provider, not editing a central
 * list - the pattern P1-BASE established so that Identity, Organisation, Access
 * and Audit arrive with their own units.
 */
final class OrganisationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * The first navigable item in SemantIQ.
         *
         * Registered after the application has booted, because the registry
         * refuses a node whose route does not resolve and the route file has not
         * been loaded while providers are still booting. That guard is worth
         * working around rather than weakening: it is what makes a menu entry
         * pointing at nothing fail in a test instead of rendering a link to a
         * 404.
         */
        $this->app->booted(function (): void {
            $this->app->make(NavigationRegistry::class)->add(new NavigationNode(
                area: ProductArea::SystemAdministration,
                label: 'Organisation',

                // A key into the central icon registry, NOT display text.
                // The shared standard names symbols i-<concept>; the shell
                // resolves this to a glyph and renders nothing for an unknown
                // key, so a bad key can never surface as words in the sidebar.
                icon: 'i-sitemap',
                routeName: 'organisation.profile',
                policyKey: 'organisation.view',
            ));
        });
    }
}
