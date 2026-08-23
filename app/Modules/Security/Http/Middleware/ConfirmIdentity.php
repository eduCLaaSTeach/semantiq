<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Middleware;

use App\Enums\Role;
use App\Modules\Security\Enums\CriticalAction;
use App\Modules\Security\Support\Reauthentication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demands a recent proof of identity before a critical action. Feature ADM-010.
 *
 * Named on a route as `confirm:tier_change`. The parameter is a
 * `CriticalAction` value, and an unrecognised one BLOCKS rather than passes: a
 * typo in a route definition must not quietly remove a control, which is the
 * same rule the permission registry follows for unknown keys.
 *
 * Runs AFTER the permission middleware on every route that uses it. The order
 * matters: authorization decides whether this person may do the thing at all,
 * and confirmation only decides whether the person at the keyboard is still
 * them. Asking somebody to prove their identity before telling them they were
 * never allowed is both a worse experience and a small disclosure.
 *
 * On refusal the person is sent to the confirmation screen with the intended
 * URL remembered, so they land back where they were rather than at the top of
 * the application.
 */
class ConfirmIdentity
{
    public function __construct(
        private readonly Reauthentication $reauthentication,
    ) {}

    public function handle(Request $request, Closure $next, string $action = ''): Response
    {
        $critical = $this->resolve($request, $action);

        if ($critical === null) {
            /*
             * The route named an action this build does not recognise. Refuse
             * rather than continue: a control that fails open when misconfigured
             * is a control that was never there.
             */
            abort(403, 'This action is gated by a confirmation rule that is not configured correctly.');
        }

        if (! $this->reauthentication->isDemandedFor($critical) || $this->reauthentication->isFresh()) {
            return $next($request);
        }

        /*
         * A non-GET request cannot simply be replayed after the detour, so the
         * person is returned to the screen they came from and asked to submit
         * again once confirmed. Replaying a stored POST would mean holding a
         * pending privileged change in the session, which is a different and
         * worse risk than one repeated form submission.
         */
        /*
         * PUT rather than flashed. A flash survives exactly one request, and
         * the confirmation is two - the form is rendered, then submitted - so a
         * flashed value would be gone by the time it was needed. The
         * alternative, posting the return URL back as a form field, would make
         * a redirect target user-controlled on the one screen a person is most
         * primed to trust.
         */
        $request->session()->put('reauthenticate.intended', $this->returnTo($request));
        $request->session()->put('reauthenticate.action', $critical->value);

        return redirect()->route('reauthenticate');
    }

    /**
     * Which critical action this request represents.
     *
     * `tier_change` escalates to `system_administrator_change` when the payload
     * says the target tier is System Administrator. ADM-010 lists the two
     * separately, and the difference is worth keeping: one is a change of what
     * somebody may do, the other hands over the platform.
     */
    private function resolve(Request $request, string $action): ?CriticalAction
    {
        $critical = CriticalAction::tryFrom($action);

        if ($critical === CriticalAction::TierChange
            && $request->input('tier') === Role::SystemAdmin->value) {
            return CriticalAction::SystemAdministratorChange;
        }

        return $critical;
    }

    /**
     * Where to send the person once they have confirmed.
     *
     * The referrer for a write, since the write itself cannot be replayed. Only
     * a same-host referrer is honoured - an open redirect that a confirmation
     * screen hands out would be a phishing gift.
     */
    private function returnTo(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');

        if ($referer !== '' && str_starts_with($referer, (string) config('app.url'))) {
            return $referer;
        }

        return $request->isMethod('GET') ? $request->fullUrl() : route('home');
    }
}
