<?php

declare(strict_types=1);

namespace App\Modules\People\Http\Controllers\Concerns;

use App\Modules\Organisation\Models\Organisation;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing, the same shape P1-01 settled on.
 *
 * A PeopleViolation becomes a stable reason and a message written for an
 * administrator - never the raw exception. Rendering an exception message is how
 * a stack trace or a database constraint reaches a browser.
 *
 * confirm() is its counterpart. P1-01 shipped a refusal channel and no success
 * channel, and the Product Owner reported it as "after Click Save nothing
 * happens" on a screen where the save had worked every time. Every write here
 * confirms itself, and a test asserts that every write CAN.
 */
trait InteractsWithPeople
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
     * A record of another organisation is NOT FOUND, not "forbidden".
     *
     * Release 1 has one organisation, so this cannot be reached through the
     * screens - which is exactly why it is asserted rather than assumed. A
     * numeric id in the address bar is the whole attack, and it costs one
     * comparison to make it answer nothing. 404 rather than 403 because a
     * refusal that distinguishes "exists but not yours" from "does not exist"
     * confirms the record exists.
     *
     * NULL is permitted: a person with no organisation belongs to nobody else's
     * either, and they are in this list precisely so they can be assigned one.
     */
    private function refuseIfOutsideOrganisation(Request $request, ?int $organisationId): void
    {
        if ($organisationId === null) {
            return;
        }

        if ($organisationId !== $this->organisation($request)->id) {
            abort(404);
        }
    }

    private function refuse(PeopleViolation $violation): RedirectResponse
    {
        $errors = ['people' => $violation->getMessage(), 'reason' => $violation->reason];

        if ($violation->blockedBy !== []) {
            $errors['blockedBy'] = implode(', ', $violation->blockedBy);
        }

        return back()->withErrors($errors);
    }

    /**
     * A successful write, confirmed.
     *
     * Past tense, business language, and NEVER a person's name or a group's
     * name - a name is business content, and this is the same channel a refusal
     * uses.
     */
    private function confirm(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('confirmation', $message);
    }
}
