<?php

namespace App\Providers;

use App\Support\Navigation;
use App\Support\Tenancy\OrganisationAwareUserProvider;
use App\Support\Tenancy\OrganisationContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(Navigation::class);

        /*
         * One organisation context per process, so the global scope, the
         * authorisation checks and the audit writer cannot disagree about
         * whose data is in play.
         */
        $this->app->singleton(OrganisationContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        /*
         * The session guard must resolve the signed-in user without the
         * organisation scope, because the context is derived from that user.
         * See OrganisationAwareUserProvider for why this is not a hole in the
         * boundary.
         */
        Auth::provider('eloquent-organisation', fn ($app, array $config) => new OrganisationAwareUserProvider(
            $app['hash'], $config['model']
        ));

        /*
         * The shell needs the same two things on every page: the navigation
         * filtered for whoever is signed in, and their initials for the account
         * control. Composing it here keeps every controller from having to
         * remember, which is how a page ends up with an empty sidebar.
         */
        View::composer('components.shell', function ($view): void {
            $user = Auth::user();

            if ($user === null) {
                $view->with(['navigation' => [], 'initials' => '']);

                return;
            }

            $navigation = $this->app->make(Navigation::class);
            $activeRoute = Route::currentRouteName();

            $view->with([
                'navigation' => $navigation->for($user, $activeRoute),
                'initials' => $this->initialsFor($user->name),
            ]);
        });
    }

    /**
     * Up to two initials for a display name, for the account control.
     *
     * Falls back to a single letter for a one-word name, and to nothing at all
     * for an empty one rather than rendering a stray character.
     */
    private function initialsFor(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($words === []) {
            return '';
        }

        $first = Str::upper(Str::substr($words[0], 0, 1));

        if (count($words) === 1) {
            return $first;
        }

        return $first.Str::upper(Str::substr($words[count($words) - 1], 0, 1));
    }
}
