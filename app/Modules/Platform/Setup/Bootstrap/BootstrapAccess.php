<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Setup\Bootstrap\Models\BootstrapAdministrator;
use Illuminate\Http\Request;

/**
 * WHETHER LOCAL PASSWORD LOGIN IS OPEN. The whole of Correction 3, in one
 * predicate that every guard reads.
 *
 * THE DEFECT THIS CLASS EXISTS TO PREVENT
 * -------------------------------------------------------------------------
 * The first draft gated the local password on BootstrapState alone:
 *
 *     open  <=>  no active User holds RoleCode::SystemAdministrator
 *
 * That predicate is computed, never stored, so it cannot drift - which is
 * exactly right for the SSH grant channel it was written for, and exactly
 * wrong here. Read it forwards:
 *
 *     the first permanent System Administrator is established  -> CONFIGURED
 *     that administrator is later deactivated                  -> UNCONFIGURED
 *     the ORIGINAL bootstrap password starts working again
 *
 * A standing local backdoor with a permanent password, reachable by
 * deactivating one account, alive for the life of the deployment. The draft
 * even carried bootstrap_administrators.disabled_at and never wrote it: the
 * column was the shape of the fix without the fix.
 *
 * THE RULE NOW
 * -------------------------------------------------------------------------
 *     open  <=>  UNCONFIGURED
 *            AND ( the principal is not closed
 *                  OR an unconsumed, unexpired recovery token has been
 *                     redeemed in THIS session )
 *
 * UNCONFIGURED remains NECESSARY and stops being SUFFICIENT. Once bootstrap
 * has closed, nothing about the administrator count reopens it; only a trusted
 * operator issuing a recovery token does, and that recovery closes again when
 * a restored SSO administrator signs in.
 *
 * WHAT IS NOT SUPERSEDED
 * -------------------------------------------------------------------------
 * BootstrapState's docblock says the operator channel reopens when every
 * System Administrator is deactivated. THAT REMAINS TRUE AND UNCHANGED for
 * P1-00's SSH grant channel, which still requires SSH, a fresh auditable grant
 * and full Entra SSO. It is superseded only for the LOCAL PASSWORD, which did
 * not exist when it was written.
 *
 * EVALUATED ON EVERY REQUEST, NEVER CACHED - D-166. A stale bootstrap session
 * must fail on its NEXT request because the guard re-reads state, not because
 * a session file was deleted. Production runs file sessions, so a shutdown
 * that depended on removing files would be defeated by one that happens to
 * remain.
 */
final class BootstrapAccess
{
    /**
     * Marks the session in which a recovery token was redeemed.
     *
     * THE CONTEXT IS WRITTEN, NOT INFERRED. Redemption does not clear
     * disabled_at as a side effect - if it did, a consumed token would leave
     * the principal permanently open and recovery would be a mode rather than
     * an episode. The token opens THIS session and nothing else.
     */
    public const RECOVERY_SESSION_KEY = 'bootstrap.recovery';

    public function __construct(private readonly BootstrapState $state) {}

    /**
     * May a local password be accepted, or a bootstrap session remain valid,
     * on this request?
     */
    public function isOpen(Request $request): bool
    {
        // NECESSARY, AND CHECKED FIRST. A deployment that has a working
        // administrator has no business accepting a local password, whatever
        // else is true - including during a recovery episode whose reason has
        // since resolved itself.
        if ($this->state->isConfigured()) {
            return false;
        }

        $principal = BootstrapAdministrator::current();

        if ($principal === null) {
            return false;
        }

        if (! $principal->isClosed()) {
            return true;
        }

        // CLOSED. Only a redeemed recovery token reopens it, and only here.
        return $request->session()->get(self::RECOVERY_SESSION_KEY) === true;
    }

    /**
     * The principal, if local password login is open on this request.
     *
     * Returning null rather than throwing keeps the refusal generic at the
     * caller: an unknown identity, a wrong password and a closed bootstrap
     * must be indistinguishable from outside, or this endpoint becomes a way
     * to ask whether a deployment has an administrator yet.
     */
    public function principal(Request $request): ?BootstrapAdministrator
    {
        return $this->isOpen($request) ? BootstrapAdministrator::current() : null;
    }
}
