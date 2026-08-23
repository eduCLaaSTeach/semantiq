<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Controllers;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Security\Enums\AuthenticationMode;
use App\Modules\Security\Http\Requests\UpdateSecurityPolicyRequest;
use App\Modules\Security\Support\AuthenticationGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Authentication Policy. Feature ADM-009.
 *
 * The screen adds three things to the generic policy form, all of them about
 * the consequence of a change rather than the change itself:
 *
 *  - what each authentication mode actually does, since "federated_only" tells
 *    an administrator nothing about who is locked out by it;
 *  - whether Microsoft Entra is CONFIGURED at all, because setting the mode to
 *    Entra-only on a deployment with no Entra registration locks everybody out
 *    including the person doing it;
 *  - how many local administrators could still get in, which is the number that
 *    decides whether turning break-glass off is prudent or catastrophic.
 */
class AuthenticationPolicyController extends SecurityPolicyController
{
    protected function screen(): string
    {
        return 'authentication';
    }

    public function edit(AuthenticationGuard $guard): View
    {
        return view('pages.admin.security-policy', array_merge($this->screenData(), [
            'modes' => AuthenticationMode::cases(),
            'entraConfigured' => $this->entraIsConfigured(),
            'localAdministrators' => $this->localAdministratorCount(),
            'lockedOutLocalAccounts' => $this->lockedOutLocalAccountCount(),
            'offersCredentialForm' => $guard->offersCredentialForm(),
            'offersFederatedSignIn' => $guard->offersFederatedSignIn(),
        ]));
    }

    public function update(UpdateSecurityPolicyRequest $request): RedirectResponse
    {
        return $this->save($request);
    }

    /**
     * Whether every value the Entra flow needs is present.
     *
     * Reports PRESENCE ONLY and never a value - SEC-DEC-017. A screen that is
     * the natural place to show "what is configured" is the natural place for a
     * client secret to end up on a page.
     */
    private function entraIsConfigured(): bool
    {
        foreach (['tenant', 'client_id', 'client_secret', 'redirect'] as $key) {
            if (blank(config('services.microsoft.'.$key))) {
                return false;
            }
        }

        return true;
    }

    /**
     * How many accounts could still sign in with a password if Entra failed.
     *
     * Scoped to the current organisation, like every other count an
     * administrator sees. A local System Administrator is the definition
     * `AuthenticationGuard::credentialRefusal()` applies, so this number and
     * that check cannot disagree about who counts.
     */
    /**
     * How many local accounts the current mode refuses at the credential form.
     *
     * Under the default mode - Entra with break-glass local administrator
     * sign-in - a local account below System Administrator cannot use the
     * password form however correct their password is. That is the intended
     * behaviour of ADM-009's "Require SSO for Business Users", and it is also
     * the thing most likely to surprise somebody on the morning after a
     * deployment, so the screen states it rather than leaving it to be
     * discovered.
     *
     * Counts only accounts that could otherwise have used it: local
     * authentication source, a password set, and permitted to authenticate.
     */
    private function lockedOutLocalAccountCount(): int
    {
        if (! app(AuthenticationGuard::class)->mode()->restrictsCredentialFormToLocalAdmins()) {
            return 0;
        }

        return User::query()
            ->inCurrentOrganisation()
            ->where('authentication_source', 'local')
            ->whereNotNull('password')
            ->where('role', '!=', Role::SystemAdmin->value)
            ->count();
    }

    private function localAdministratorCount(): int
    {
        return User::query()
            ->inCurrentOrganisation()
            ->where('authentication_source', 'local')
            ->where('role', Role::SystemAdmin->value)
            ->whereNotNull('password')
            ->count();
    }
}
