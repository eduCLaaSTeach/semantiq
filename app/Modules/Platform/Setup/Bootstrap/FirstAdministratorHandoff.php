<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use App\Modules\Platform\Bootstrap\GrantIssuer;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use RuntimeException;

/**
 * STEP 7. NOMINATING THE FIRST PERMANENT SYSTEM ADMINISTRATOR.
 *
 * CORRECTION 2. The first draft said the nomination is an email field and the
 * administrator then "signs in normally". That cannot work, and
 * CallbackController shows why in one line:
 *
 *     $grant = $request->session()->pull(BeginController::SESSION_GRANT);
 *     $user  = is_string($grant) && $grant !== ''
 *            ? $this->completeBootstrap($grant, $identity)   // the ONLY path
 *            : …                                             //  that creates
 *                                                            //  the first
 *                                                            //  administrator
 *
 * AN ORDINARY SIGN-IN WITH NO GRANT IN SESSION CREATES NOBODY. The nomination
 * screen would have "worked", the nominated person would have signed in, been
 * refused as an unknown identity, and the installation would have been left
 * with no administrator and no explanation.
 *
 * THERE IS NO SECOND FIRST-ADMINISTRATOR MECHANISM HERE. This class issues an
 * ORDINARY bootstrap grant through the existing GrantIssuer, unchanged, and
 * the existing GrantRedeemer remains the one authority that creates the first
 * System Administrator. All of P1-00's semantics come with it: Str::random(64),
 * SHA-256 at rest, a 30-minute TTL, single-use atomic consumption, and refusal
 * WITHOUT consumption on a wrong identity (D-03 rule 7) so the grant remains
 * usable by the right person.
 *
 * THE ONLY NEW THING IS WHO ASKS. An authenticated bootstrap principal at a
 * screen, instead of an operator at an SSH prompt.
 *
 * THE TENANT IS READ FROM THE VERIFIED STORED CONFIGURATION, NEVER FROM THE
 * FORM. A grant is bound to the tenant it was issued for and checked against
 * that binding at redemption, so taking the tenant from user input would let a
 * typo produce a grant that can never be redeemed - discoverable only by the
 * nominated administrator, at the moment they are trying to sign in.
 *
 * THE LINK IS RETURNED ONCE AND NEVER PERSISTED. It is displayed on screen for
 * the operator to convey out of band. It is NOT emailed: email is OPTIONAL in
 * First-Run (D-171), and making a mandatory step depend on an optional one
 * would let an installation reach step 7 and be unable to finish. Leaving the
 * screen loses it, and a fresh grant must be issued - which is a property, not
 * a limitation.
 */
final class FirstAdministratorHandoff
{
    public function __construct(
        private readonly GrantIssuer $issuer,
        private readonly IdentityConfigurationSource $identityConfiguration,
    ) {}

    /**
     * @return string the one-time First-Run URL, returned ONCE
     */
    public function nominate(string $emailOrUpn): string
    {
        $subject = mb_strtolower(trim($emailOrUpn));

        if ($subject === '') {
            throw new RuntimeException('A nominated administrator is required.');
        }

        $identity = $this->identityConfiguration->resolve();

        if ($identity->tenantId === '') {
            // A grant bound to no tenant could never be redeemed. Refusing here
            // is the difference between a clear message now and an unusable
            // link discovered by somebody else later.
            throw new RuntimeException(
                'Microsoft sign-in has not been configured yet, so an administrator cannot be nominated.',
            );
        }

        $grant = $this->issuer->issue($subject, $identity->tenantId, 'first-run');

        return route('first_run.begin', ['grant' => $grant]);
    }
}
