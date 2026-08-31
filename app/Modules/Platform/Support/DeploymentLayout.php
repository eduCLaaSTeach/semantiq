<?php

declare(strict_types=1);

namespace App\Modules\Platform\Support;

/**
 * Decides where the web-servable directory is, for whichever layout is running.
 *
 * SemantIQ runs in two layouts and must behave identically in both:
 *
 *   Repository / CI / local   base/            base/public/index.php
 *                             assets at        base/public/build/
 *
 *   Production (D-08B)        public_html/     public_html/index.php
 *                             assets at        public_html/build/
 *
 * Production serves directly from public_html. There is no public_html/public
 * layer - that path was only an early Git-to-cPanel synchronisation test
 * location and is not part of the architecture. The repository keeps its normal
 * Laravel public/ directory because Vite, `artisan serve` and the test suite
 * expect it; that is a repository concern and implies nothing about the server.
 *
 * WHY DETECTION RATHER THAN A SERVER ENVIRONMENT VALUE
 *
 * The obvious approach - set the path in the front controller - is wrong, and
 * wrong in a way that hides. `semantiq:health` checks for the Vite manifest
 * under public_path(), and it runs under the CLI during deployment, which never
 * loads index.php. A path set only there would be correct for every web request
 * and silently wrong for Artisan, the health check and any future command.
 *
 * The next approach - a new APP_PUBLIC_PATH in the server .env - would work,
 * but it puts a value that determines whether the application can find its own
 * assets into the one file that is hand-maintained, unversioned, and exists in
 * exactly one copy. Getting it wrong is a broken deployment with no diff to
 * point at.
 *
 * So the layout is derived from the layout itself. A front controller at the
 * base path IS the root layout - that is what the root layout means, not a flag
 * describing it - and bootstrap/app.php is loaded by index.php and artisan
 * alike, so both agree by construction. Nothing to configure, nothing to keep
 * in sync, and a test asserts the repository never grows a base-path index.php
 * that would make a developer machine impersonate production.
 */
final class DeploymentLayout
{
    /**
     * True when the front controller sits at the base path - the production
     * layout, where public_html is both the deployment root and the document
     * root.
     */
    public static function isRootLayout(string $basePath): bool
    {
        return is_file(rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'index.php');
    }

    /**
     * The directory the web server serves from, for this layout.
     */
    public static function publicPath(string $basePath): string
    {
        $basePath = rtrim($basePath, DIRECTORY_SEPARATOR);

        return self::isRootLayout($basePath)
            ? $basePath
            : $basePath.DIRECTORY_SEPARATOR.'public';
    }

    /**
     * Whether Laravel's conventional public/storage symlink may be created.
     *
     * Under the root layout it may NOT, and this is not a preference. The link
     * target would be public_path('storage'), which resolves to
     * public_html/storage - the application's real storage directory, holding
     * the logs, cache, sessions and compiled views. Creating the link there
     * would attempt to replace live runtime state with a symlink to a subset of
     * itself. The route that would serve those files is already disabled for
     * the same collision (see RoutePrefixCollisionTest), and any later need to
     * serve user files goes through an authorised controller, which this
     * security model requires regardless.
     */
    public static function allowsPublicStorageLink(string $basePath): bool
    {
        return ! self::isRootLayout($basePath);
    }
}
