<?php

/*
 * SemantIQ production front controller.
 *
 * Staged by the deploy workflow to public_html/index.php, the same way
 * public_html.htaccess is staged to public_html/.htaccess. It is NOT named
 * index.php in the repository, and must never be: DeploymentLayout decides the
 * layout by whether a front controller sits at the base path, so a base-path
 * index.php in the repository would make every developer machine and CI run
 * believe it was production. A test enforces its absence.
 *
 * The difference from Laravel's stock public/index.php is only the paths. The
 * stock controller walks UP one directory because it lives inside public/;
 * here it sits at the deployment root among its siblings, so each require is
 * relative to __DIR__ itself. Getting that wrong is a white screen, not an
 * error message, because the autoloader is what would have reported it.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
