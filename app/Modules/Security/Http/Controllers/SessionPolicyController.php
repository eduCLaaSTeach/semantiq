<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Models\User;
use App\Modules\Identity\Services\UserRegistry;
use App\Modules\Security\Enums\CriticalAction;
use App\Modules\Security\Http\Requests\UpdateSecurityPolicyRequest;
use App\Modules\Security\Support\SecurityCapabilities;
use App\Modules\Security\Support\SessionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Session Policy. Feature ADM-010.
 *
 * The screen this gate learned the most from. Two of its controls - concurrent
 * session limits and ending somebody else's sessions - depend on the session
 * driver, and production runs a driver that cannot support either.
 *
 * HOW THAT IS PRESENTED, and why it matters. The unavailable controls are shown
 * with their state and a plain sentence naming the driver in force and the one
 * needed. The REVOCATION ACTION IS NOT RENDERED AT ALL when it cannot work:
 * gate 3 rule 10. A greyed-out button is still a button, and somebody will
 * eventually believe they pressed it. The route refuses independently, because
 * a control that is only absent from a page is not an access control.
 */
class SessionPolicyController extends SecurityPolicyController
{
    protected function screen(): string
    {
        return 'sessions';
    }

    public function edit(SecurityCapabilities $capabilities, SessionRegistry $sessions, Request $request): View
    {
        $viewer = $request->user();

        return view('pages.admin.security-policy', array_merge($this->screenData(), [
            'criticalActions' => (array) config('security.critical_actions', []),
            'deferredActions' => CriticalAction::deferred(),
            'sessionDriver' => $capabilities->sessionDriver(),
            'sessionsAreEnumerable' => $capabilities->canEnumerateSessions(),
            'sessionBlocker' => $capabilities->sessionEnumerationBlocker(),
            /*
             * The viewer's own sessions, not a directory of everybody's. A page
             * listing every live session in the organisation would be a
             * targeting list, and ADM-010 asks for revocation rather than
             * surveillance. Ending another account's sessions is done from that
             * account's page, where the administrator already knows who they
             * are acting on.
             */
            'ownSessions' => $viewer instanceof User
                ? $sessions->liveFor($viewer, (string) $request->session()->getId())
                : [],
        ]));
    }

    public function update(UpdateSecurityPolicyRequest $request): RedirectResponse
    {
        return $this->save($request);
    }

    /**
     * End every session belonging to one account.
     *
     * The subject is resolved through `UserRegistry`, not by route model
     * binding, so the organisation boundary is enforced by the same guard every
     * other mutation uses (SEC-DEC-033). A cross-organisation id gets a 404
     * from there rather than a session-ending action.
     */
    public function revoke(
        Request $request,
        UserRegistry $registry,
        SessionRegistry $sessions,
        int $user,
    ): RedirectResponse {
        /*
         * Resolved through the registry's own scoped query and then checked
         * again with the registry's tenancy guard. Two steps rather than route
         * model binding, because binding would load ANY id in the table:
         * `users` carries no global organisation scope (SEC-DEC-022), so the
         * boundary has to be asked for explicitly on every path that acts on an
         * account.
         *
         * 404 rather than 403, per SEC-DEC-034: a 403 confirms the id exists
         * and belongs to somebody, and the ids are sequential integers.
         */
        $subject = $registry->query()->find($user);

        if ($subject === null || ! $registry->isInOrganisation($subject)) {
            abort(404);
        }

        $ended = $sessions->revokeAllFor($subject);

        if ($ended === null) {
            /*
             * Either the driver cannot do it or policy has it switched off.
             * Both are already audited by the registry. The message says what
             * is true rather than "something went wrong", because the person
             * reading it is the one who can change it.
             */
            return back()->withErrors([
                'sessions' => 'Sessions could not be ended. '
                    .(app(SecurityCapabilities::class)->sessionEnumerationBlocker()
                        ?? 'Administrator-initiated revocation is switched off on this screen.'),
            ]);
        }

        return back()->with('status', $ended === 0
            ? 'That account had no signed-in sessions to end.'
            : $ended.' session'.($ended === 1 ? '' : 's').' ended for that account.');
    }
}
