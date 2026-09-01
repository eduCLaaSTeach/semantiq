<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Providers;

use App\Shared\Navigation\ApprovedMenu;
use App\Shared\Navigation\NavigationRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * The Organisation module: structure, and nothing that decides access.
 *
 * It also registers the complete approved roadmap menu (D-19), because
 * Organisation is currently the only delivered capability and therefore the
 * only module that can register a node whose route resolves. When P1-02
 * delivers Identity & SSO, its entry moves from locked() to leaf() inside
 * ApprovedMenu and this registration is unchanged.
 */
final class OrganisationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Registered after the application has booted, because the registry
         * refuses a node whose route does not resolve and the route file has
         * not been loaded while providers are still booting. That guard is
         * worth working around rather than weakening: it is what makes a menu
         * entry pointing at nothing fail in a test instead of rendering a link
         * to a 404.
         */
        $this->app->booted(function (): void {
            $registry = $this->app->make(NavigationRegistry::class);

            foreach (ApprovedMenu::roadmap() as $node) {
                $registry->add($node);
            }
        });
    }
}
