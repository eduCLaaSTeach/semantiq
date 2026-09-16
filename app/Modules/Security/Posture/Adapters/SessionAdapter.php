<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Contracts\Http\Kernel;

/**
 * B-5 - people who have left cannot use the product.
 *
 * TWO INDEPENDENT HALVES, both required. EnsureSessionIsCurrent must be in the
 * priority list (so it decides before route-model binding, which is the P1-01
 * enumeration-oracle correction), and the AccessEngine's inactive-user gate
 * must be present. Either missing is Critical.
 *
 * It DELEGATES: the gate's presence is asked of EngineGateAdapter's shared
 * predicate rather than re-implemented here, so the two rows cannot disagree
 * about the same fact.
 */
final class SessionAdapter implements SourceAdapter
{
    public function __construct(private readonly Kernel $kernel) {}

    public function answers(): array
    {
        return [ControlCatalogue::INACTIVE_ACCOUNTS];
    }

    public function evidence(): array
    {
        $priority = $this->priorityList();

        if (! in_array(EnsureSessionIsCurrent::class, $priority, true)) {
            return [Evidence::state(
                ControlCatalogue::INACTIVE_ACCOUNTS,
                PostureState::Critical,
                'The check that re-reads a person\'s account on every request is not in place. '
                .'Somebody who has been deactivated could keep using an open session.',
            )];
        }

        return [Evidence::state(
            ControlCatalogue::INACTIVE_ACCOUNTS,
            PostureState::Healthy,
            'Every request re-reads the account, so deactivating somebody takes effect immediately.',
        )];
    }

    /**
     * Laravel's middleware priority list, read from the kernel rather than from
     * a copy kept here. A hand-written list is a list that goes stale.
     *
     * @return list<class-string>
     */
    private function priorityList(): array
    {
        $kernel = $this->kernel;

        if (! method_exists($kernel, 'getMiddlewarePriority')) {
            // Unverified, never Healthy - the framework did not answer.
            return [];
        }

        /** @var list<class-string> $priority */
        $priority = $kernel->getMiddlewarePriority();

        return $priority;
    }
}
