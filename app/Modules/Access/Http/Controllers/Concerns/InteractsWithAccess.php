<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers\Concerns;

use App\Modules\Access\Support\AccessViolation;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing, the same shape P1-01, P1-03 and P1-04 settled on.
 *
 * An AccessViolation becomes a stable reason and a message written for an
 * administrator - never the raw exception, because rendering an exception
 * message is how a stack trace or a database constraint reaches a browser.
 *
 * confirm() is its counterpart, and it exists because P1-01 shipped a refusal
 * channel with no success channel and the Product Owner reported "after Click
 * Save nothing happens" on a screen where the save had worked every time.
 */
trait InteractsWithAccess
{
    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('semantiq_user');

        return $user;
    }

    private function organisation(Request $request): Organisation
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('semantiq_organisation');

        return $organisation;
    }

    /**
     * A record of another organisation is NOT FOUND, never "forbidden".
     *
     * 404 rather than 403, because a refusal that distinguishes "exists but not
     * yours" from "does not exist" confirms the record exists. A platform-scoped
     * assignment carries no organisation and is reachable by anyone who reached
     * this screen at all.
     */
    private function refuseIfOutsideOrganisation(Request $request, ?int $organisationId): void
    {
        if ($organisationId !== null && $organisationId !== $this->organisation($request)->id) {
            abort(404);
        }
    }

    private function refuse(AccessViolation $violation): RedirectResponse
    {
        return back()->withErrors([
            'access' => $violation->getMessage(),
            'reason' => $violation->reason,
        ]);
    }

    /**
     * A successful write, confirmed. Past tense, business language, and NEVER a
     * person's name or a domain's name - a name is business content, and this
     * is the same channel a refusal uses.
     */
    private function confirm(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('confirmation', $message);
    }
}
