<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Auth;

use App\Modules\Platform\Bootstrap\BootstrapState;
use App\Modules\Platform\Bootstrap\GrantRedeemer;
use App\Modules\Platform\Http\Controllers\FirstRun\BeginController;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Identity\AuthenticationFailed;
use App\Modules\Platform\Identity\IdentityProvider;
use App\Modules\Platform\Identity\IdentityResolver;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The single registered redirect URI.
 *
 * Bootstrap and normal sign-in share it, distinguished by intent held in the
 * session rather than by a second registered URI - one URI registered in Entra,
 * exactly as D-04 approved.
 */
final class CallbackController
{
    public function __construct(
        private readonly IdentityProvider $provider,
        private readonly IdentityResolver $resolver,
        private readonly GrantRedeemer $redeemer,
        private readonly BootstrapState $state,
        private readonly SecurityEventLogger $events,
    ) {}

    public function __invoke(Request $request): Response
    {
        $grant = $request->session()->pull(BeginController::SESSION_GRANT);

        try {
            $identity = $this->provider->completeAuthorization($request);

            $user = is_string($grant) && $grant !== ''
                ? $this->completeBootstrap($grant, $identity)
                : $this->resolver->resolve($identity);
        } catch (AuthenticationFailed $failure) {
            $this->recordProtocolFailure($failure);

            return redirect()->route("auth.{$failure->state}");
        }

        $this->issueSession($request, $user);

        return redirect()->route('console.home');
    }

    private function completeBootstrap(string $grant, $identity): User
    {
        // Closed between the grant being issued and redeemed: refuse rather than
        // create a second administrator through a stale link.
        if ($this->state->isConfigured()) {
            throw AuthenticationFailed::protocol('bootstrap_closed');
        }

        return $this->redeemer->redeem($grant, $identity);
    }

    /**
     * Session identifier regenerated on the privilege transition from anonymous
     * to authenticated - session fixation is otherwise trivial.
     */
    private function issueSession(Request $request, User $user): void
    {
        $request->session()->regenerate();

        $request->session()->put(EnsureSessionIsCurrent::SESSION_USER_ID, $user->id);
        $request->session()->put(
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT,
            now()->toIso8601String(),
        );

        $this->events->record(SecurityEventLogger::LOGIN_SUCCEEDED, [
            'provider' => $user->provider,
            'subject' => $user->external_subject,
            'tenant' => $user->tenant_id,
            'user_id' => $user->id,
            'result' => 'succeeded',
        ]);
    }

    /**
     * The resolver and redeemer already logged their own refusals with the
     * detail they had. Only protocol and tenant failures are logged here, so a
     * single refusal never produces two events.
     */
    private function recordProtocolFailure(AuthenticationFailed $failure): void
    {
        $event = match ($failure->state) {
            AuthenticationFailed::STATE_ACCESS_DENIED => SecurityEventLogger::LOGIN_REFUSED_TENANT,
            AuthenticationFailed::STATE_UNAVAILABLE => SecurityEventLogger::LOGIN_REFUSED_PROTOCOL,
            default => null,
        };

        if ($event === null) {
            return;
        }

        $this->events->record($event, [
            'result' => 'refused',
            'reason' => $failure->reason,
        ]);
    }
}
