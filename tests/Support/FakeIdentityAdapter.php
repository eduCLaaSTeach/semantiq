<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Security\Posture\Adapters\IdentityAdapter;
use App\Modules\Security\Posture\PostureState;
use ReflectionMethod;

/**
 * Reaches IdentityAdapter's private state mapping, so N-SS8 can prove that an
 * UNRECOGNISED stored value becomes Unverified.
 *
 * Testing it through a fixture is not possible: P1-02 only ever produces its
 * four declared states, so the default branch is unreachable from outside. It
 * still has to be right, because the value it protects against is one a FUTURE
 * unit could introduce, and the mutation - a match with no default - would be a
 * crash on a screen whose whole job is to keep reporting.
 */
final class FakeIdentityAdapter
{
    public function __construct(private readonly string $rowState) {}

    public function mapForTest(): PostureState
    {
        $method = new ReflectionMethod(
            IdentityAdapter::class,
            'map',
        );

        return $method->invoke(
            app(IdentityAdapter::class),
            $this->rowState,
        );
    }
}
