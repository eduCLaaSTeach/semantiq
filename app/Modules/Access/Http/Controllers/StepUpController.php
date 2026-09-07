<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Http\Controllers\Concerns\InteractsWithAccess;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\StepUp\PendingStepUp;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * D-73 step-up re-authentication.
 *
 * THREE STEPS: confirm what is about to happen, go to Microsoft, come back and
 * perform exactly that action once.
 *
 * The middle step is Microsoft's, not SemantIQ's. There is no password screen
 * here and no dialog pretending to be step-up - the identity provider performs
 * the re-authentication, and this application only proves it happened by
 * reading the provider's auth_time.
 *
 * WHAT THIS CANNOT PROVE. SemantIQ can verify that Microsoft reports a fresh
 * authentication event. It cannot independently prove which credential or
 * factor Microsoft required, unless the tenant's Entra authentication policy
 * provides and guarantees that assurance. Nothing here says "MFA verified", and
 * nothing should be added that does.
 */
final class StepUpController
{
    use InteractsWithAccess;

    public function __construct(
        private readonly StepUpService $stepUp,
        private readonly IdentityProvider $provider,
        private readonly RoleAssignmentService $roles,
        private readonly EntitlementService $entitlements,
    ) {}

    /**
     * The confirmation card. Says what is about to be confirmed, in business
     * language, before anybody is sent anywhere.
     */
    public function begin(Request $request, string $reference): Response|RedirectResponse
    {
        try {
            $pending = $this->stepUp->resolve($reference, $this->actor($request), $request->session()->getId());
        } catch (AccessViolation $violation) {
            return $this->refuseToIndex($violation);
        }

        return Inertia::render('Access/StepUp', [
            'reference' => $reference,
            'action' => $pending->action->value,
            'description' => $pending->action->description(),
            'expiresInMinutes' => PendingStepUp::LIFETIME_MINUTES,
        ]);
    }

    /**
     * Send the administrator to Microsoft.
     *
     * The reference is carried in the session for the return trip rather than
     * in the redirect, so it does not appear in the provider's logs or in a
     * browser history entry.
     *
     * AN ORDINARY REDIRECT DOES NOT LEAVE AN INERTIA PAGE. The confirmation
     * card submits over XHR, and an XHR FOLLOWS a 302 itself: the browser
     * fetched Microsoft's sign-in page in the background, Inertia discarded the
     * response because it was not an Inertia payload, and the page simply sat
     * there. No error, no navigation, nothing - which is exactly what the
     * Product Owner reported, and what a browser trace confirmed: the request
     * to login.microsoftonline.com was made and the top-level document never
     * moved.
     *
     * Inertia::location is the answer to precisely this. It replies 409 with an
     * X-Inertia-Location header, and the Inertia client performs a real
     * top-level navigation instead of trying to render the response.
     *
     * NOTHING ABOUT D-73 IS RELAXED. The departure is still a POST, still CSRF
     * protected, still refuses unless the reference resolves for this person in
     * this session, and the reference is still stored server-side. Only the way
     * the browser is told to leave has changed.
     */
    public function redirect(Request $request, string $reference): HttpResponse|RedirectResponse
    {
        try {
            $this->stepUp->resolve($reference, $this->actor($request), $request->session()->getId());
        } catch (AccessViolation $violation) {
            return $this->refuseToIndex($violation);
        }

        $request->session()->put(self::SESSION_REFERENCE, $reference);

        try {
            $departure = $this->provider->beginStepUpAuthorization(route('auth.microsoft.step-up'));
        } catch (AuthenticationFailed) {
            return $this->refuseToIndex(AccessViolation::stepUpInvalid());
        }

        /*
         * Inertia::location HANDLES BOTH CALLERS ITSELF - 409 with
         * X-Inertia-Location for an Inertia request, and the RedirectResponse
         * returned untouched for anything else.
         *
         * The first version wrote that branch out by hand. It was redundant,
         * and worse, unfalsifiable: a mutation collapsing it to
         * Inertia::location() alone changed no observable behaviour and
         * SURVIVED. A branch no test can distinguish is a branch that should
         * not exist, so it does not.
         */
        return Inertia::location($departure);
    }

    public const SESSION_REFERENCE = 'access.step_up.reference';

    /**
     * The return.
     *
     * EVERY FAILURE PATH PERFORMS NO ACTION AND CONSUMES THE REFERENCE.
     * Provider error, cancellation, expiry, a stale auth_time, a replay - all
     * of them end the pending row. Leaving it alive "so they can try again"
     * converts a cancelled step-up into a reusable one, which is exactly the
     * change somebody makes to be helpful.
     */
    public function callback(Request $request): RedirectResponse
    {
        $actor = $this->actor($request);
        $reference = $request->session()->pull(self::SESSION_REFERENCE);

        if (! is_string($reference) || $reference === '') {
            return $this->refuseToIndex(AccessViolation::stepUpInvalid());
        }

        try {
            $pending = $this->stepUp->resolve($reference, $actor, $request->session()->getId());
        } catch (AccessViolation $violation) {
            return $this->refuseToIndex($violation);
        }

        try {
            $verification = $this->provider->completeStepUpAuthorization($request);
        } catch (AuthenticationFailed) {
            // Covers both the provider returning an error and the user
            // cancelling at Microsoft - the provider reports a cancellation as
            // an error response, and both mean the same thing here: no action.
            $this->stepUp->abandon($pending, 'provider_error', $actor);

            return redirect()
                ->route('access.index')
                ->with('confirmation', 'The action was cancelled and has not been applied.');
        }

        // The returning identity must be the SAME person. A different account
        // authenticating freshly would otherwise satisfy somebody else's
        // pending privileged action.
        if (! hash_equals($actor->external_subject, $verification->identity->subject)
            || ! hash_equals($actor->tenant_id, $verification->identity->tenant)) {
            $this->stepUp->abandon($pending, 'identity_mismatch', $actor);

            return $this->refuseToIndex(AccessViolation::stepUpInvalid());
        }

        try {
            $this->stepUp->verifyFreshness($pending, $verification->authenticatedAt, $actor);
        } catch (AccessViolation $violation) {
            return $this->refuseToIndex($violation);
        }

        try {
            $redirect = $this->stepUp->consumeAndPerform(
                $pending,
                $actor,
                fn (PendingStepUp $confirmed): RedirectResponse => $this->perform($request, $confirmed, $actor),
            );
        } catch (AccessViolation $violation) {
            return $this->refuseToIndex($violation);
        }

        return $redirect;
    }

    /**
     * The privileged write, INSIDE the transaction that consumed the reference.
     *
     * Every parameter comes from the stored row. Nothing is read from the
     * request, because the request came back through the browser and anything
     * in it could have been changed on the way.
     */
    private function perform(Request $request, PendingStepUp $pending, User $actor): RedirectResponse
    {
        return match ($pending->action) {
            StepUpAction::GrantSystemAdministrator,
            StepUpAction::GrantOrganisationAdministrator => $this->performGrant($pending, $actor),

            /*
             * SELF-GRANT COVERS BOTH HALVES OF D-73 - granting yourself a role,
             * and granting your own assignment a domain. One approved action,
             * two stored shapes, told apart by which exact target was written
             * down when the confirmation began.
             *
             * A row carrying neither shape is malformed and DENIES. Falling
             * through to the role branch would hand a null role code to
             * RoleCode::from.
             */
            StepUpAction::SelfGrant => $pending->role_assignment_id !== null
                ? $this->performSelfEntitlementGrant($pending, $actor)
                : $this->performGrant($pending, $actor),

            StepUpAction::RevokeSystemAdministrator => $this->performRevoke($pending, $actor),

            StepUpAction::GrantRestrictedSensitivity => $this->performCeiling($pending, $actor),
        };
    }

    private function performGrant(PendingStepUp $pending, User $actor): RedirectResponse
    {
        if ($pending->role_code === null || $pending->subject_user_id === null) {
            throw AccessViolation::stepUpInvalid();
        }

        $role = RoleCode::from((string) $pending->role_code);
        $subject = User::query()->findOrFail($pending->subject_user_id);

        $assignment = $this->roles->assign(
            $subject,
            $role,
            $role->isPlatformScoped() ? null : $pending->organisation_id,
            $actor,
        );

        return $this->confirm('access.show', 'Role granted.', $assignment->id);
    }

    /**
     * The self-granted domain entitlement, performed from the STORED target.
     *
     * Nothing is read from the request. The assignment and the domain are the
     * two the administrator confirmed, found by their stored ids - so changing
     * the URL, the form or the browser's history between the confirmation and
     * the return substitutes nothing.
     *
     * AND THE AUTHORITY IS RE-EVALUATED HERE, not merely at the confirmation.
     * The administrator has been away at Microsoft; the assignment may have
     * been revoked, the tenancy may no longer match, and the entitlement may
     * already exist. EntitlementService::grant re-checks the last three under
     * its own locks; what only this method can re-check is that the action is
     * still the SELF-grant, in the organisation, that was approved.
     */
    private function performSelfEntitlementGrant(PendingStepUp $pending, User $actor): RedirectResponse
    {
        if ($pending->business_domain_id === null) {
            throw AccessViolation::stepUpInvalid();
        }

        $assignment = RoleAssignment::query()->findOrFail($pending->role_assignment_id);
        $domain = BusinessDomain::query()->findOrFail($pending->business_domain_id);

        // Still the same person's own assignment. If it is not, this is no
        // longer the action that was confirmed.
        if ($assignment->user_id !== $actor->getKey()) {
            throw AccessViolation::stepUpInvalid();
        }

        // And still inside the organisation the confirmation was begun in.
        if ($assignment->organisation_id !== null
            && $assignment->organisation_id !== $pending->organisation_id) {
            throw AccessViolation::stepUpInvalid();
        }

        $this->entitlements->grant($assignment, $domain, $actor);

        return $this->confirm(
            'access.show',
            'Domain entitlement granted. Assign a scope to make it effective.',
            $assignment->getKey(),
        );
    }

    private function performRevoke(PendingStepUp $pending, User $actor): RedirectResponse
    {
        $assignment = RoleAssignment::query()
            ->where('user_id', $pending->subject_user_id)
            ->where('role_code', (string) $pending->role_code)
            ->whereNull('ended_at')
            ->firstOrFail();

        $this->roles->revoke($assignment, $actor);

        return $this->confirm('access.show', 'Role revoked.', $assignment->id);
    }

    private function performCeiling(PendingStepUp $pending, User $actor): RedirectResponse
    {
        $entitlement = DomainEntitlement::query()->findOrFail($pending->domain_entitlement_id);

        $this->entitlements->setCeiling(
            $entitlement,
            Sensitivity::from((string) $pending->sensitivity),
            $actor,
        );

        return $this->confirm('access.show', 'Sensitivity level set.', $entitlement->role_assignment_id);
    }

    private function refuseToIndex(AccessViolation $violation): RedirectResponse
    {
        return redirect()
            ->route('access.index')
            ->withErrors(['access' => $violation->getMessage(), 'reason' => $violation->reason]);
    }
}
