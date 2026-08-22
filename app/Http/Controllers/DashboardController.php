<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Navigation;
use Illuminate\Contracts\View\View;

/**
 * The Workspace dashboard, and for now the only built destination in the shell.
 *
 * It deliberately shows what exists rather than a wall of placeholder metrics:
 * a dashboard reporting zeroes from tables that have no rows would look like a
 * broken application rather than an early one.
 */
class DashboardController extends Controller
{
    public function __invoke(Navigation $navigation): View
    {
        return view('shell.dashboard', [
            'trail' => $navigation->trailFor('dashboard'),
        ]);
    }
}
