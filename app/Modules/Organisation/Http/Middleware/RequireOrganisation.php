<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Middleware;

use App\Modules\Organisation\Services\OrganisationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Structure cannot exist before the organisation it belongs to.
 *
 * Until the Company Profile is created there is nothing to hang a legal entity,
 * business unit, department or team from - and under D-16 there is also nobody
 * associated with an organisation, so every membership rule would refuse anyway.
 * Sending the administrator to the Company Profile is the honest answer.
 */
final class RequireOrganisation
{
    public function __construct(private readonly OrganisationService $organisations) {}

    public function handle(Request $request, Closure $next): Response
    {
        $organisation = $this->organisations->current();

        if ($organisation === null) {
            return $request->expectsJson()
                ? response()->json(['message' => 'No organisation has been created yet.'], 409)
                : redirect()->route('organisation.profile');
        }

        $request->attributes->set('semantiq_organisation', $organisation);

        return $next($request);
    }
}
