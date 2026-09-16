<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Modules\Security\Posture\Aggregation;
use App\Modules\Security\Posture\PostureState;
use PHPUnit\Framework\TestCase;

/**
 * N-SS1 to N-SS4, N-SS3a/3b/3c - THE AGGREGATION CONTRACT.
 *
 * Exercised against Aggregation::of() DIRECTLY rather than through a screen, so
 * a swapped pair fails even when no rendered page would have shown the
 * difference. A contract tested only through the UI is a contract tested only
 * where somebody happened to look.
 */
final class AggregationTest extends TestCase
{
    /** N-SS1. Every state renders with its business label, never a code. */
    public function test_every_state_has_a_business_label(): void
    {
        $labels = [
            PostureState::Critical->label() => 'Act now',
            PostureState::Attention->label() => 'Needs attention',
            PostureState::Unverified->label() => 'Not verified',
            PostureState::NotApplicable->label() => 'Not part of Release 1',
            PostureState::Healthy->label() => 'Healthy',
        ];

        foreach ($labels as $actual => $expected) {
            $this->assertSame($expected, $actual);
        }

        /*
         * And no label is an INTERNAL IDENTIFIER, which is the mutation:
         * return $this->value.
         *
         * Asserted as a shape rather than as "not equal to the name" - the
         * Healthy case's label legitimately IS the word Healthy, so a
         * not-equal-to-name assertion would fail on correct code. What must
         * never appear is the stored form: lower case with underscores, like
         * not_applicable.
         */
        foreach (PostureState::cases() as $state) {
            $this->assertNotSame($state->value, $state->label());

            $this->assertDoesNotMatchRegularExpression(
                '/^[a-z]+(_[a-z]+)+$/',
                $state->label(),
                "The label for {$state->value} reads like a stored value rather than English.",
            );
        }
    }

    /**
     * N-SS2. THE HEADLINE CASE.
     *
     * Mutation: copy IdentityHealthReport::state() - drop Unverified from the
     * precedence loop, so "NotChecked contributes nothing". A report whose every
     * row is unverified would then return Healthy, which is exactly "green
     * because nothing is being measured".
     */
    public function test_an_all_unverified_set_aggregates_to_unverified_and_never_healthy(): void
    {
        $result = Aggregation::of([
            PostureState::Unverified,
            PostureState::Unverified,
            PostureState::Unverified,
        ]);

        $this->assertSame(PostureState::Unverified, $result);
        $this->assertNotSame(PostureState::Healthy, $result);
    }

    /**
     * N-SS3. Healthy only when EVERY applicable control is healthy.
     *
     * Mutation: return Healthy when merely no Critical row exists.
     */
    public function test_healthy_requires_every_applicable_control_to_be_healthy(): void
    {
        $this->assertSame(
            PostureState::Healthy,
            Aggregation::of([PostureState::Healthy, PostureState::Healthy]),
        );

        $this->assertSame(
            PostureState::Attention,
            Aggregation::of([PostureState::Healthy, PostureState::Attention]),
            'A set containing an attention row must not aggregate to healthy merely because '
            .'nothing is critical.',
        );
    }

    /**
     * N-SS3a. Healthy + NotApplicable is HEALTHY.
     *
     * Mutation: put NotApplicable back into the precedence chain between
     * Unverified and Healthy. A fully healthy deployment could then never reach
     * Healthy, because one permanently-out-of-scope row would hold the
     * aggregate down forever - which is the same failure as false green,
     * arrived at from the other side.
     */
    public function test_a_not_applicable_row_cannot_hold_a_healthy_deployment_down(): void
    {
        $this->assertSame(
            PostureState::Healthy,
            Aggregation::of([
                PostureState::Healthy,
                PostureState::NotApplicable,
                PostureState::Healthy,
            ]),
        );
    }

    /**
     * N-SS3b. Unverified + Healthy is UNVERIFIED.
     *
     * Mutation: let a healthy row outvote an unverified one.
     */
    public function test_a_healthy_row_never_outvotes_an_unverified_one(): void
    {
        $this->assertSame(
            PostureState::Unverified,
            Aggregation::of([PostureState::Healthy, PostureState::Unverified, PostureState::Healthy]),
        );
    }

    /**
     * N-SS3c. All NotApplicable is NOT_APPLICABLE - not healthy, not unverified.
     *
     * Mutation: return Healthy for an empty applicable set. An aggregate over
     * nothing is not a claim of health.
     *
     * D-83: this case is FIXTURE-ONLY in Release 1. No artificial
     * not_applicable row is rendered, and that is a decision rather than an
     * omission - the contract has to be right before the first real instance
     * arrives, not retrofitted around it.
     */
    public function test_an_entirely_not_applicable_set_aggregates_to_not_applicable(): void
    {
        $this->assertSame(
            PostureState::NotApplicable,
            Aggregation::of([PostureState::NotApplicable, PostureState::NotApplicable]),
        );

        $this->assertSame(
            PostureState::NotApplicable,
            Aggregation::of([]),
            'An aggregate over nothing at all is not a claim of health either.',
        );
    }

    /**
     * N-SS4. Precedence holds for every adjacent APPLICABLE pair.
     *
     * Pair by pair rather than as one long list, so a swapped pair fails even
     * if no screen would have shown it. Mutation: swap two states in
     * Aggregation::PRECEDENCE.
     */
    public function test_precedence_holds_for_every_adjacent_applicable_pair(): void
    {
        $order = [
            PostureState::Critical,
            PostureState::Attention,
            PostureState::Unverified,
            PostureState::Healthy,
        ];

        for ($i = 0; $i < count($order) - 1; $i++) {
            $stronger = $order[$i];
            $weaker = $order[$i + 1];

            $this->assertSame(
                $stronger,
                Aggregation::of([$weaker, $stronger]),
                "{$stronger->value} must win over {$weaker->value}.",
            );

            // And in the other order, so the result is not an artefact of
            // position in the array.
            $this->assertSame(
                $stronger,
                Aggregation::of([$stronger, $weaker]),
                "{$stronger->value} must win over {$weaker->value} whichever order they arrive in.",
            );
        }
    }

    /** Only NotApplicable is non-contributing. Mutation: flip contributes(). */
    public function test_exactly_one_state_does_not_contribute(): void
    {
        $nonContributing = array_values(array_filter(
            PostureState::cases(),
            static fn (PostureState $s): bool => ! $s->contributes(),
        ));

        $this->assertSame([PostureState::NotApplicable], $nonContributing);
    }

    /**
     * N-SS36's half of the contract. NotApplicable and Healthy are not
     * exceptions; everything else is.
     *
     * Mutation: make isException() return $this !== Healthy, so a
     * not_applicable row pads a list that can then never reach zero.
     */
    public function test_only_non_healthy_applicable_states_are_exceptions(): void
    {
        $this->assertTrue(PostureState::Critical->isException());
        $this->assertTrue(PostureState::Attention->isException());
        $this->assertTrue(PostureState::Unverified->isException());

        $this->assertFalse(PostureState::Healthy->isException());
        $this->assertFalse(PostureState::NotApplicable->isException());
    }
}
