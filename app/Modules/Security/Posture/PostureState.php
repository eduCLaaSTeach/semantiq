<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * THE FIVE STATES. There is no sixth, and `not_configured` is deliberately
 * absent - a MANDATORY control that is not configured is Critical, not a gentle
 * amber it can sit in forever.
 *
 * NotApplicable is a DISPLAY state. It is reserved for something genuinely
 * outside Release 1, and it is NOT a resting place for an applicable control
 * whose evidence is unavailable: that case is Unverified, every time. Encryption
 * is the worked example - it plainly applies to this product, SemantIQ simply
 * cannot observe it from inside. Calling that "not applicable" would quietly
 * write the control out of scope.
 *
 * Nothing renders a case NAME. label() is what a person reads, and N-SS1 breaks
 * it by returning a code.
 */
enum PostureState: string
{
    case Critical = 'critical';
    case Attention = 'attention';
    case Unverified = 'unverified';
    case NotApplicable = 'not_applicable';
    case Healthy = 'healthy';

    /** What a person reads. Never the case name, never the value. */
    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Act now',
            self::Attention => 'Needs attention',
            self::Unverified => 'Not verified',
            self::NotApplicable => 'Not part of Release 1',
            self::Healthy => 'Healthy',
        };
    }

    /**
     * Whether this state takes part in aggregation.
     *
     * Only NotApplicable does not. Expressed here, once, so Aggregation reads
     * as a filter over a property rather than as a special case somebody can
     * forget to copy.
     */
    public function contributes(): bool
    {
        return $this !== self::NotApplicable;
    }

    /**
     * Whether a row in this state is an unresolved Exception.
     *
     * NotApplicable is not - it belongs in the "Not part of Release 1" area.
     * Healthy is not. Everything else is. N-SS36 breaks it by counting a
     * NotApplicable row among the unresolved.
     */
    public function isException(): bool
    {
        return $this !== self::Healthy && $this !== self::NotApplicable;
    }
}
