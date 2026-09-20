<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Bootstrap;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * D-159's second half: THE LOCAL PASSWORD, AGAIN, BEFORE A PRIVILEGED CHANGE.
 *
 * WHY NOT MICROSOFT STEP-UP HERE. Microsoft step-up is the normal
 * administrator's re-authentication, and during First-Run Microsoft may not
 * exist yet - that is the entire reason First-Run exists. A rule that required
 * it would make the setup flow unsatisfiable on exactly the deployment it was
 * built for, which D-159 names explicitly.
 *
 * WHAT IT GUARDS. The same class of change as the console step-up: replacing or
 * removing an established credential, changing an established Identity
 * configuration, and issuing the first permanent administrator handoff - which
 * is the single most privileged act in the product, because it decides who runs
 * the deployment.
 *
 * THE PASSWORD IS READ FROM THE REQUEST BODY AND DISCARDED.
 *
 *   - never put in the session, in any form;
 *   - never logged;
 *   - never in an Audit context - ALLOWED_KEYS has no key that could hold it,
 *     which is what makes that structural rather than a promise;
 *   - never echoed back in a refusal or a validation message.
 *
 * THE REFUSAL IS GENERIC. It says the password was not accepted and nothing
 * else - not whether the principal is closed, not whether recovery is open, not
 * which half failed.
 *
 * IT GOES THROUGH THE SAME CREDENTIAL BOUNDARY AS SIGN-IN, including the
 * unusable-hash check: a closed principal must refuse here rather than raise,
 * for the same reason it must refuse at sign-in.
 */
final class BootstrapReconfirmation
{
    public function __construct(private readonly BootstrapAccess $access) {}

    /**
     * Is this request accompanied by the current local password?
     *
     * @param  string|null  $password  from the request BODY only
     */
    public function confirms(Request $request, ?string $password): bool
    {
        if (! is_string($password) || $password === '') {
            return false;
        }

        // The live predicate, not the session. A principal that has closed
        // since the page was rendered must not be able to reconfirm.
        $principal = $this->access->principal($request);

        if ($principal === null) {
            return false;
        }

        /*
         * THE SAME UNUSABLE-HASH CHECK AS SIGN-IN.
         *
         * Hash::check raises rather than returning false when the stored value
         * is not a recognisable hash, and BootstrapCloser deliberately stores
         * one that is not. Without this, reconfirming against a closed
         * principal would produce a 500 with a stack trace instead of the
         * generic refusal - an error page that appears for exactly one reason
         * is a disclosure.
         */
        if (Hash::info($principal->password_hash)['algoName'] === 'unknown') {
            return false;
        }

        return Hash::check($password, $principal->password_hash);
    }
}
