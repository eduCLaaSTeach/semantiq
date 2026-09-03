<?php

declare(strict_types=1);

namespace App\Modules\Domains\Http\Controllers\Concerns;

use App\Modules\Domains\Support\DomainViolation;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing, the same shape P1-01 and P1-03 settled on.
 *
 * A DomainViolation becomes a stable reason and a message written for an
 * administrator - never the raw exception. Rendering an exception message is
 * how a stack trace or a database constraint reaches a browser.
 *
 * confirm() is its counterpart, and it exists because P1-01 shipped a refusal
 * channel with no success channel: the Product Owner reported "after Click Save
 * nothing happens" on a screen where the save had worked every time.
 */
trait InteractsWithDomains
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
     * A domain of another organisation is NOT FOUND, never "forbidden".
     *
     * Release 1 has one organisation, so this cannot be reached through the
     * screens - which is exactly why it is asserted rather than assumed. A
     * numeric id in the address bar is the whole attack. 404 rather than 403
     * because a refusal that distinguishes "exists but not yours" from "does
     * not exist" confirms the record exists.
     */
    private function refuseIfOutsideOrganisation(Request $request, int $organisationId): void
    {
        if ($organisationId !== $this->organisation($request)->id) {
            abort(404);
        }
    }

    private function refuse(DomainViolation $violation): RedirectResponse
    {
        $errors = ['domains' => $violation->getMessage(), 'reason' => $violation->reason];

        if ($violation->blockedBy !== []) {
            $errors['blockedBy'] = implode(', ', $violation->blockedBy);
        }

        return back()->withErrors($errors);
    }

    /**
     * A successful write, confirmed.
     *
     * Past tense, business language, and NEVER a domain's name or a person's
     * name - a name is business content, and this is the same channel a refusal
     * uses.
     */
    private function confirm(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('confirmation', $message);
    }
}
