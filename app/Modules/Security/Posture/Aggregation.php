<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * THE AGGREGATION CONTRACT. The only place the precedence rule exists.
 *
 * A second implementation - a summary component that works out the worst row, a
 * severity ranking array, an Exceptions screen that re-filters instead of
 * reading the projection - would drift from this within a unit or two, and then
 * two parts of one screen would disagree about the same deployment. N-SS34 is
 * an architecture guard that greps for exactly that.
 *
 * THE MUTATION THIS IS BUILT AGAINST. IdentityHealthReport::state() reads "any
 * Failed; else any Degraded; else Healthy", and its own docblock says
 * "NotChecked contributes nothing - it is information, not a finding". That is
 * defensible on a single-purpose identity screen. It is FATAL as a posture
 * aggregate: a report whose every row is NotChecked returns Healthy, which is
 * exactly "green because nothing is being measured". Dropping Unverified from
 * the loop below is the single most likely wrong edit in this unit, because it
 * is CORRECT IN THE FILE IT WOULD BE COPIED FROM. N-SS2 and N-SS3b break it.
 *
 * P1-02 IS NOT CHANGED. Its rule is right for its screen. P1-06 maps its four
 * row states in and never calls its state().
 */
final class Aggregation
{
    /**
     * The applicable precedence, in order. NotApplicable is deliberately absent.
     */
    private const PRECEDENCE = [
        PostureState::Critical,
        PostureState::Attention,
        PostureState::Unverified,
    ];

    /**
     * @param  list<PostureState>  $contributions
     */
    public static function of(array $contributions): PostureState
    {
        /*
         * NotApplicable is filtered out BEFORE precedence is applied, so it is
         * not a rank and cannot hold an aggregate down.
         *
         * An earlier draft of the PLAN ordered it BETWEEN Unverified and
         * Healthy while also requiring every contributor to be Healthy. Those
         * two statements are incompatible: a deployment in which every
         * applicable control is genuinely healthy could never reach Healthy,
         * because one permanently-out-of-scope row would hold the aggregate
         * down forever. A posture screen that cannot ever say Healthy is a
         * posture screen people stop reading - the same failure as false green,
         * arrived at from the other side. N-SS3a breaks it.
         */
        $applicable = array_values(array_filter(
            $contributions,
            static fn (PostureState $state): bool => $state->contributes(),
        ));

        /*
         * An empty APPLICABLE set is NotApplicable - never Healthy, never
         * Unverified. An aggregate over nothing is not a claim of health.
         *
         * The wholly empty case is unreachable in production because
         * ControlCatalogue is non-empty and N-SS7 asserts it, but it is
         * answered here rather than left to fall through to the Healthy return
         * below. N-SS3c breaks it.
         */
        if ($applicable === []) {
            return PostureState::NotApplicable;
        }

        foreach (self::PRECEDENCE as $state) {
            if (in_array($state, $applicable, true)) {
                return $state;
            }
        }

        /*
         * Healthy is reached ONLY by falling through every other applicable
         * state - never by "no critical row exists". That difference is the
         * whole governing sentence: absence of evidence is never evidence of
         * health.
         */
        return PostureState::Healthy;
    }
}
