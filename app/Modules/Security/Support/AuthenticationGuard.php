<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Enums\Role;
use App\Models\User;
use App\Modules\Security\Enums\AuthenticationMode;

/**
 * The decisions ADM-009's Authentication Policy actually makes.
 *
 * One class rather than a set of `if` statements spread across the two sign-in
 * controllers. Both paths ask the same questions - is this way in offered at
 * all, may this person use it, is this tenant allowed, is this address allowed,
 * how many attempts before a lockout - and the answers have to agree. When they
 * lived in two controllers they could not be tested together, and a policy that
 * two code paths interpret differently is not a policy.
 *
 * THIS CLASS DECIDES; IT DOES NOT DISCLOSE. Every refusal here returns a
 * machine-readable reason for the audit trail. What the person is TOLD is the
 * controller's business, and the two paths deliberately differ:
 *
 *  - The credential form returns one sentence for every failure, because
 *    nobody has proved anything yet and naming the state would confirm to an
 *    anonymous visitor that an address belongs to a real person here
 *    (SEC-DEC-027).
 *  - The Microsoft path names the state, because Entra has already proved who
 *    the person is and it is their own account (SEC-DEC-032).
 *
 * Keeping the decision here and the disclosure there is what lets both remain
 * true at once.
 */
class AuthenticationGuard
{
    public function __construct(
        private readonly SecurityPolicies $policies,
    ) {}

    public function mode(): AuthenticationMode
    {
        return AuthenticationMode::tryFrom($this->policies->text('sign_in.mode'))
            /*
             * An unrecognised stored mode falls back to the SAFEST option, not
             * to the catalogue default. A value this code does not understand
             * means something is wrong, and the wrong end of that is offering a
             * password form nobody meant to offer.
             */
            ?? AuthenticationMode::FederatedOnly;
    }

    /** Whether the credential form is rendered at all. */
    public function offersCredentialForm(): bool
    {
        return $this->mode()->allowsCredentialForm()
            && ($this->mode() === AuthenticationMode::LocalOnly || $this->policies->enabled('sign_in.allow_local_admin'));
    }

    /** Whether the Microsoft button is rendered at all. */
    public function offersFederatedSignIn(): bool
    {
        return $this->mode()->allowsFederatedSignIn();
    }

    /** How many failed attempts before the lockout, from policy. */
    public function attemptThreshold(): int
    {
        return $this->policies->number('sign_in.failed_attempt_threshold');
    }

    /** How long the attempt counter holds, in seconds, from policy. */
    public function lockSeconds(): int
    {
        return $this->policies->number('sign_in.lock_minutes') * 60;
    }

    /** Whether an unknown Entra person is given an account. */
    public function maySelfProvision(): bool
    {
        return $this->policies->enabled('sign_in.auto_create_users');
    }

    /**
     * Why the credential form must refuse this account, or null to allow it.
     *
     * Called AFTER the password has been verified, for the same reason
     * `User::mayAuthenticate()` is: checking first would turn the form into a
     * directory lookup that says which addresses exist here.
     *
     * @return string|null a reason for the AUDIT TRAIL, never for the browser
     */
    public function credentialRefusal(User $user): ?string
    {
        $mode = $this->mode();

        if (! $mode->allowsCredentialForm()) {
            return 'Authentication policy is "'.$mode->value.'": the credential form is not accepted.';
        }

        if ($mode === AuthenticationMode::LocalOnly) {
            return null;
        }

        /* FederatedWithLocalAdmin from here down. */

        if (! $this->policies->enabled('sign_in.allow_local_admin')) {
            return 'Break-glass local administrator sign-in is turned off.';
        }

        if ($user->authentication_source !== 'local') {
            /*
             * A federated account has no business coming through this form even
             * if a password hash survives on the row from before it was
             * federated. That stale hash is exactly the credential an attacker
             * would like to use.
             */
            return 'Account signs in through Microsoft Entra; the credential form does not accept it.';
        }

        if (! $user->hasAtLeast(Role::SystemAdmin)) {
            return 'Only a local System Administrator may use the credential form while Entra is the primary path.';
        }

        return null;
    }

    /**
     * Why a Microsoft-authenticated person must be refused, or null to allow.
     *
     * Two checks ADM-009 names explicitly: validate the tenant, and hold the
     * allowed email domains. Both are allow-lists that are EMPTY BY DEFAULT and
     * therefore permissive by default, which is stated on the screen rather
     * than left for somebody to discover.
     *
     * @return string|null a reason for the audit trail
     */
    public function federatedRefusal(?string $tenantId, string $email): ?string
    {
        if (! $this->mode()->allowsFederatedSignIn()) {
            return 'Authentication policy is "local_only": Microsoft sign-in is not accepted.';
        }

        $allowedTenant = trim($this->policies->text('sign_in.allowed_tenant_id'));

        if ($allowedTenant !== '') {
            /*
             * A missing `tid` claim is a REFUSAL, not a pass. An allow-list
             * that waves through anything it cannot identify is not an
             * allow-list.
             */
            if ($tenantId === null || ! hash_equals($allowedTenant, $tenantId)) {
                return 'The directory this sign-in came from is not the allowed tenant.';
            }
        }

        $allowedDomains = $this->policies->entries('sign_in.allowed_email_domains');

        if ($allowedDomains !== [] && ! $this->domainIsAllowed($email, $allowedDomains)) {
            return 'The address used is outside the allowed email domains.';
        }

        return null;
    }

    /**
     * Whether an address belongs to one of the allowed domains.
     *
     * Matched on the part after the LAST `@`, lowercased. Sub-domains are not
     * implied: `contoso.com` does not admit `mail.contoso.com`, because a
     * wildcard nobody asked for is an allow-list that grew on its own. A
     * customer who wants both lists both.
     *
     * @param  list<string>  $allowed
     */
    private function domainIsAllowed(string $email, array $allowed): bool
    {
        $at = strrpos($email, '@');

        if ($at === false) {
            return false;
        }

        $domain = strtolower(substr($email, $at + 1));

        foreach ($allowed as $candidate) {
            /* A leading "@" is what somebody types when they mean a domain, so
             * it is accepted and stripped rather than silently never matching. */
            if (strtolower(ltrim($candidate, '@')) === $domain) {
                return true;
            }
        }

        return false;
    }
}
