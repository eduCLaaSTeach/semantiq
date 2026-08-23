<?php

declare(strict_types=1);

namespace App\Modules\Security\Support;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Security\Enums\AuthenticationMode;
use App\Modules\Security\Enums\ConcurrentSessionPolicy;
use App\Modules\Security\Enums\SecretStatus;
use App\Modules\Security\Enums\SecurityStatus;
use App\Modules\Security\Models\SecretReference;
use Illuminate\Support\Collection;

/**
 * The roll-up behind Security Overview.
 *
 * Decision D5, approved 25 August 2026: the Security Overview leaf has no
 * feature of its own in the Release 1 specification, so it is built as a
 * READ-ONLY summary of ADM-009 to ADM-012 and invents no policy and no control.
 * Every number on it is read from something one of those four features owns.
 *
 * WHAT MAKES IT WORTH HAVING rather than four links. Each policy screen answers
 * "what does this say"; none of them answers "is any of it a problem right
 * now". A weak authentication mode, a session control that cannot run on this
 * driver, a certificate that lapses in nine days and a control this application
 * cannot verify are four different findings on four different screens, and an
 * administrator who has to visit four screens to notice them will not.
 *
 * NEVER REPORTS HEALTHY FOR SOMETHING IT CANNOT SEE. Gate 3 rule 9. Where a
 * fact is not verifiable - an expiry date nobody entered, a provider this
 * application does not call - the answer is Not Verified, which is a different
 * badge and a different colour from green.
 */
class SecurityPosture
{
    public function __construct(
        private readonly SecurityPolicies $policies,
        private readonly SecurityCapabilities $capabilities,
        private readonly ApiSecurityAudit $controls,
    ) {}

    /**
     * ADM-009. How people get in, and how strong that is.
     *
     * @return array{status: SecurityStatus, headline: string, detail: string, notes: list<string>}
     */
    public function authentication(): array
    {
        $mode = AuthenticationMode::tryFrom($this->policies->text('sign_in.mode'))
            ?? AuthenticationMode::FederatedOnly;

        $notes = [];
        $status = SecurityStatus::Healthy;

        if ($mode === AuthenticationMode::LocalOnly) {
            $status = SecurityStatus::Warning;
            $notes[] = 'Every account signs in with a password held by this application. Microsoft Entra is not in use.';
        }

        if ($this->policies->enabled('sign_in.auto_create_users')) {
            $status = SecurityStatus::worst([$status, SecurityStatus::Warning]);
            $notes[] = 'Anybody the directory authenticates is given a SemantIQ account automatically.';
        }

        if (! $this->policies->enabled('sign_in.require_federated_for_business_users')
            && $mode !== AuthenticationMode::LocalOnly) {
            $status = SecurityStatus::worst([$status, SecurityStatus::Warning]);
            $notes[] = 'Business users are not required to come through Microsoft Entra.';
        }

        if (trim($this->policies->text('sign_in.allowed_tenant_id')) === ''
            && $mode->allowsFederatedSignIn()) {
            /*
             * NOT a warning. A blank tenant means the check falls back to the
             * application registration, which is a real constraint - it is just
             * one this screen cannot see, so it is reported as unverified
             * rather than as either safe or broken.
             */
            $status = SecurityStatus::worst([$status, SecurityStatus::NotVerified]);
            $notes[] = 'No allowed tenant is set here, so which directory is accepted depends on the Entra application registration rather than on a value SemantIQ can check.';
        }

        if ($this->policies->entries('sign_in.allowed_email_domains') === []
            && $mode->allowsFederatedSignIn()) {
            $notes[] = 'No email domain allow-list is set, so any address inside the tenant may sign in - including a guest carrying their own domain.';
            $status = SecurityStatus::worst([$status, SecurityStatus::Warning]);
        }

        return [
            'status' => $status,
            'headline' => $mode->label(),
            'detail' => sprintf(
                'Lockout after %d failed attempts for %d minute(s).',
                $this->policies->number('sign_in.failed_attempt_threshold'),
                $this->policies->number('sign_in.lock_minutes'),
            ),
            'notes' => $notes,
        ];
    }

    /**
     * ADM-010. How long a session lasts and what it takes to prove yourself.
     *
     * @return array{status: SecurityStatus, headline: string, detail: string, notes: list<string>}
     */
    public function sessions(): array
    {
        $notes = [];
        $status = SecurityStatus::Healthy;

        $idle = $this->policies->number('activity.idle_minutes');
        $maximum = $this->policies->number('activity.maximum_minutes');

        if (! $this->policies->enabled('activity.confirm_critical_actions')) {
            $status = SecurityStatus::Warning;
            $notes[] = 'A critical action does not ask the person to prove who they are again, so an unlocked machine is enough to make one.';
        }

        if ($this->policies->number('activity.remember_me_days') > 0) {
            $status = SecurityStatus::worst([$status, SecurityStatus::Warning]);
            $notes[] = 'Remember-me is on, so a sign-in survives the browser closing and outlives the maximum session duration.';
        }

        $concurrency = ConcurrentSessionPolicy::tryFrom($this->policies->text('activity.concurrent_policy'))
            ?? ConcurrentSessionPolicy::Unlimited;

        if (! $this->capabilities->canEnumerateSessions()) {
            /*
             * Not Available rather than Warning. The code is correct and the
             * environment cannot support it; that is a different problem with a
             * different owner, and colouring it amber would put a permanent
             * warning on the screen that nobody can clear from the screen.
             */
            $status = SecurityStatus::worst([$status, SecurityStatus::NotAvailable]);
            $notes[] = (string) $this->capabilities->sessionEnumerationBlocker();

            if ($concurrency->requiresSessionEnumeration()) {
                $notes[] = 'The concurrent session policy is set to "'.$concurrency->label()
                    .'" but is NOT being applied, because this deployment cannot list a person\'s sessions.';
                $status = SecurityStatus::worst([$status, SecurityStatus::Warning]);
            }
        }

        return [
            'status' => $status,
            'headline' => $idle.' minute idle timeout, '.$maximum.' minute maximum',
            'detail' => $this->capabilities->canEnumerateSessions()
                ? 'Concurrent sessions: '.$concurrency->label().'. Sessions can be listed and ended.'
                : 'Session driver "'.$this->capabilities->sessionDriver().'": sessions cannot be listed or ended.',
            'notes' => $notes,
        ];
    }

    /**
     * ADM-011. The controls the application applies to every request.
     *
     * @return array{status: SecurityStatus, headline: string, detail: string, notes: list<string>}
     */
    public function application(): array
    {
        $controls = $this->controls->run();

        $failing = array_values(array_filter(
            $controls,
            static fn (array $control): bool => $control['status']->needsAttention(),
        ));

        return [
            'status' => SecurityStatus::worst(array_map(
                static fn (array $control): SecurityStatus => $control['status'],
                $controls,
            )),
            'headline' => (count($controls) - count($failing)).' of '.count($controls).' controls healthy',
            'detail' => 'Checked against the running application, not against a stored value.',
            'notes' => array_map(
                static fn (array $control): string => $control['name'].': '.$control['detail'],
                $failing,
            ),
        ];
    }

    /**
     * ADM-012. What credentials this system depends on and when they lapse.
     *
     * @return array{status: SecurityStatus, headline: string, detail: string, notes: list<string>}
     */
    public function secrets(): array
    {
        $references = SecretReference::query()->active()->get();

        if ($references->isEmpty()) {
            return [
                'status' => SecurityStatus::NotConfigured,
                'headline' => 'No secret references recorded',
                'detail' => 'Nothing here yet. This deployment certainly depends on credentials; none of them has been recorded.',
                'notes' => [],
            ];
        }

        $statuses = $references->map(
            static fn (SecretReference $reference): SecretStatus => $reference->status(),
        );

        $notes = [];

        foreach ([
            [SecretStatus::Expired, 'have already expired'],
            [SecretStatus::ExpiringSoon, 'expire within '.SecretStatus::EXPIRY_HORIZON_DAYS.' days'],
            [SecretStatus::RotationDue, 'are due for rotation'],
            [SecretStatus::Unknown, 'have no expiry or rotation date recorded, so nothing is tracking them'],
        ] as [$state, $phrase]) {
            $count = $statuses->filter(static fn (SecretStatus $s): bool => $s === $state)->count();

            if ($count > 0) {
                $notes[] = $count.' reference'.($count === 1 ? '' : 's').' '.$phrase.'.';
            }
        }

        return [
            'status' => SecurityStatus::worst($statuses->map(
                static fn (SecretStatus $status): SecurityStatus => $status->overviewStatus(),
            )->all()),
            'headline' => $references->count().' active reference'.($references->count() === 1 ? '' : 's'),
            'detail' => 'Expiry dates are what an administrator entered. SemantIQ does not contact any provider to confirm them.',
            'notes' => $notes,
        ];
    }

    /** References expiring within the horizon, or already expired. */
    public function expiringReferences(): Collection
    {
        return SecretReference::query()
            ->expiringWithin(SecretStatus::EXPIRY_HORIZON_DAYS)
            ->orderBy('expires_on')
            ->get();
    }

    /** References whose rotation date has arrived. */
    public function rotationDueReferences(): Collection
    {
        return SecretReference::query()
            ->rotationDue()
            ->orderBy('rotation_due_on')
            ->get();
    }

    /**
     * Everything that needs attention, gathered from the four postures.
     *
     * @return list<array{area: string, status: SecurityStatus, message: string}>
     */
    public function warnings(): array
    {
        $warnings = [];

        foreach ([
            'Authentication' => $this->authentication(),
            'Sessions' => $this->sessions(),
            'Application security' => $this->application(),
            'Secret references' => $this->secrets(),
        ] as $area => $posture) {
            foreach ($posture['notes'] as $note) {
                $warnings[] = [
                    'area' => $area,
                    'status' => $posture['status'],
                    'message' => $note,
                ];
            }
        }

        return $warnings;
    }

    /**
     * The gaps that are configuration rather than opinion.
     *
     * Separate from `warnings()` because these have a single, specific fix and
     * the fix is not on any of these screens. A warning says "this is weaker
     * than it could be"; a gap says "this is not set up".
     *
     * @return list<array{title: string, detail: string}>
     */
    public function configurationGaps(): array
    {
        $gaps = [];

        foreach (['tenant', 'client_id', 'client_secret', 'redirect'] as $key) {
            if (blank(config('services.microsoft.'.$key))) {
                /* PRESENCE ONLY, never the value - SEC-DEC-017. */
                $gaps[] = [
                    'title' => 'Microsoft Entra is not fully configured',
                    'detail' => 'At least one value the sign-in flow needs is missing from the server environment. '
                        .'Until it is set, Microsoft sign-in cannot be used and the authentication mode must not be set to Entra-only.',
                ];

                break;
            }
        }

        if (! $this->capabilities->canEnumerateSessions()) {
            $gaps[] = [
                'title' => 'Sessions cannot be listed or ended',
                'detail' => (string) $this->capabilities->sessionEnumerationBlocker(),
            ];
        }

        if (SecretReference::query()->active()->count() === 0) {
            $gaps[] = [
                'title' => 'No credentials are being tracked',
                'detail' => 'This deployment depends on at least a database password and, once Entra is set up, a client secret. '
                    .'None is recorded, so nothing will warn anybody before one lapses.',
            ];
        }

        return $gaps;
    }

    /**
     * The most recent security-relevant audit events.
     *
     * Filtered to the Security module rather than showing everything: the
     * overview is a security page, and a list dominated by ordinary
     * administration would bury the sign-in failures.
     */
    public function recentEvents(int $limit = 10): Collection
    {
        return AuditEvent::query()
            ->where('module', 'Security')
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();
    }

    /** The worst of the four postures, which is the posture of the whole. */
    public function overall(): SecurityStatus
    {
        return SecurityStatus::worst([
            $this->authentication()['status'],
            $this->sessions()['status'],
            $this->application()['status'],
            $this->secrets()['status'],
        ]);
    }
}
