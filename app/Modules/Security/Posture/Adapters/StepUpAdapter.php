<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Routing\Router;

/**
 * B-9a / B-9b, and the same two facts as PR-9a / PR-9b.
 *
 * SPLIT DELIBERATELY, BECAUSE SEMANTIQ CAN ONLY SEE ONE HALF.
 *
 *   B-9a  SemantIQ's side - the routes, the action catalogue and the local
 *         return address. Anything missing is CRITICAL: privileged changes
 *         would be unconfirmable.
 *
 *   B-9b  Microsoft's side accepting the return. THERE IS NO ACCEPTED RUNTIME
 *         EVIDENCE SOURCE, so it is UNVERIFIED - always, in Release 1.
 *
 * A CONFIGURED LOCAL REDIRECT URI DOES NOT PROVE MICROSOFT HAS REGISTERED IT.
 * P1-05 produced this exact failure in production: the local configuration was
 * right and step-up still failed, because Entra had not registered the URI. A
 * single row would have been rendered green on the strength of its local half.
 * Two rows cannot be.
 *
 * B-9b'S IMPLEMENTATION IS A CONSTANT, AND THAT IS THE POINT. There is no
 * branch that could make it Healthy, because there is no evidence that could
 * justify one - so it cannot be made to lie by a configuration change. When a
 * future unit introduces an accepted live source, the way P1-02's probe is an
 * accepted source for reachability, B-9b gains an adapter. N-SS12a breaks it by
 * reading B-9a's evidence.
 *
 * Both screens read this one adapter, so Secure Baseline and Privileged Access
 * Health cannot disagree about the same fact.
 */
final class StepUpAdapter implements SourceAdapter
{
    /** Every route step-up needs. Missing one makes the journey impossible. */
    private const ROUTES = [
        'auth.microsoft.step-up',
        'access.step-up.begin',
        'access.step-up.redirect',
    ];

    public function __construct(private readonly Router $router) {}

    public function answers(): array
    {
        return [
            ControlCatalogue::STEP_UP_LOCAL,
            ControlCatalogue::STEP_UP_EXTERNAL,
            ControlCatalogue::STEP_UP_LOCAL_PRIVILEGED,
            ControlCatalogue::STEP_UP_EXTERNAL_PRIVILEGED,
        ];
    }

    public function evidence(): array
    {
        $local = $this->localHalf();
        $external = $this->externalHalf();

        return [
            Evidence::state(ControlCatalogue::STEP_UP_LOCAL, $local[0], $local[1]),
            Evidence::state(ControlCatalogue::STEP_UP_EXTERNAL, $external[0], $external[1]),
            Evidence::state(ControlCatalogue::STEP_UP_LOCAL_PRIVILEGED, $local[0], $local[1]),
            Evidence::state(ControlCatalogue::STEP_UP_EXTERNAL_PRIVILEGED, $external[0], $external[1]),
        ];
    }

    /** @return array{0: PostureState, 1: string} */
    private function localHalf(): array
    {
        $missing = [];

        foreach (self::ROUTES as $name) {
            if ($this->router->getRoutes()->getByName($name) === null) {
                $missing[] = $name;
            }
        }

        if ($missing !== []) {
            return [
                PostureState::Critical,
                'The confirmation journey is incomplete, so a privileged change could not be '
                .'confirmed with a fresh Microsoft sign-in.',
            ];
        }

        if (StepUpAction::cases() === []) {
            return [
                PostureState::Critical,
                'No action is listed as needing a fresh Microsoft sign-in, so privileged changes '
                .'would go through unconfirmed.',
            ];
        }

        $actions = count(StepUpAction::cases());

        return [
            PostureState::Healthy,
            "{$actions} kinds of privileged change ask the person to confirm their identity with "
            .'Microsoft again before anything is granted.',
        ];
    }

    /**
     * ALWAYS UNVERIFIED. Deliberately not a branch.
     *
     * @return array{0: PostureState, 1: string}
     */
    private function externalHalf(): array
    {
        return [
            PostureState::Unverified,
            'SemantIQ\'s side of this is configured. Whether Microsoft accepts the return address '
            .'cannot be checked from inside SemantIQ, and will first be proven the next time '
            .'somebody confirms their identity.',
        ];
    }
}
