<?php

namespace App\Providers;

use App\Support\Navigation;
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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
