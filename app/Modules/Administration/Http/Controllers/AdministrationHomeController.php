<?php

declare(strict_types=1);

namespace App\Modules\Administration\Http\Controllers;

use App\Modules\Administration\Support\AdministrationHomeProjection;
use App\Modules\Platform\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ADMINISTRATION HOME. ONE GET, AND NOTHING ELSE.
 *
 * Nothing on this screen changes anything, so there is nothing for a second
 * verb to do. AdministrationHomeIsAProjectionTest asserts the Administration
 * route set as an EQUALITY rather than by naming the routes that do exist, so
 * a POST added here fails the build.
 *
 * IT COMPOSES NOTHING ITSELF. The controller resolves the viewer and hands the
 * whole question to one projection, for the reason RendersPosture already
 * records: a controller that assembled the payload could forget a projection,
 * and the part it would forget is the part that decides what this viewer may
 * be told.
 *
 * EVERY DESTINATION ON THE SCREEN RE-AUTHORISES ON ARRIVAL. A link here is a
 * suggestion, never a grant, and the projection renders none the viewer cannot
 * open.
 */
final class AdministrationHomeController
{
    public function __construct(private readonly AdministrationHomeProjection $projection) {}

    public function show(Request $request): Response
    {
        /** @var User $viewer */
        $viewer = $request->attributes->get('semantiq_user');

        return Inertia::render('Administration/Home', $this->projection->for($viewer));
    }
}
