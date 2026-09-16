<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * INTERNAL TRUTH for one posture control. Never rendered.
 *
 * The constructor is private and the only factory is reachable through
 * PostureEvaluator, so a controller or a React component cannot construct one.
 * That is half of "exactly one evaluator"; the other half is that Aggregation
 * holds the only copy of the precedence rule.
 *
 * Rendering goes through PostureProjection, which turns this into a ViewerRow
 * (valued) or a WithheldRow (named, with no state field at all).
 */
final class PostureRow
{
    private function __construct(
        public readonly string $control,
        public readonly string $label,
        public readonly ControlScope $scope,
        public readonly ExceptionKind $kind,
        public readonly PostureState $state,
        public readonly string $finding,
        public readonly ?string $ownerRoute,
        public readonly ?string $ownerLabel,

        /*
         * What this row is ABOUT, when the label alone is not enough.
         *
         * A domain row's label is its facet - "Accountable owner" - which reads
         * correctly under a heading naming the domain and means nothing in the
         * Exceptions list. The qualifier carries the domain name so Exceptions
         * can say WHICH domain, and the domain section can leave it out rather
         * than printing the name twice.
         */
        public readonly ?string $qualifier = null,
    ) {}

    /**
     * Constructed by PostureEvaluator only.
     *
     * @internal
     */
    public static function make(
        string $control,
        string $label,
        ControlScope $scope,
        ExceptionKind $kind,
        PostureState $state,
        string $finding,
        ?string $ownerRoute,
        ?string $ownerLabel,
        ?string $qualifier = null,
    ): self {
        return new self($control, $label, $scope, $kind, $state, $finding, $ownerRoute, $ownerLabel, $qualifier);
    }
}
