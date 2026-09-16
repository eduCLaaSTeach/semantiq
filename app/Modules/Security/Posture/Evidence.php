<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * What an adapter returns: an answer about ONE catalogued control.
 *
 * An adapter may COUNT and may READ STATE. It may never DECIDE - every decision
 * is delegated to the unit that owns it, so a posture screen cannot become a
 * second opinion about access.
 *
 * THE ERROR BOUNDARY IS HERE. `finding` is business copy chosen by the adapter
 * from its own fixed sentences, or produced by unavailable() below. A raw
 * exception message, a SQL error, a filesystem path, a provider payload or an
 * environment value must never reach it - those are the strings that carry a
 * connection string, a table name or a secret into a rendered prop.
 * PostureEvaluator catches Throwable and calls unavailable(), which DISCARDS
 * the exception entirely rather than formatting it.
 */
final class Evidence
{
    private function __construct(
        public readonly string $control,
        public readonly ?PostureState $state,
        public readonly ?int $count,
        public readonly string $finding,
    ) {}

    /** A posture control's answer. */
    public static function state(string $control, PostureState $state, string $finding): self
    {
        return new self($control, $state, null, $finding);
    }

    /** An informational metric's answer. A count and context, and NO state. */
    public static function count(string $control, int $count, string $finding): self
    {
        return new self($control, null, $count, $finding);
    }

    /**
     * The fail-closed answer.
     *
     * $why is a REASON CODE from the adapter's own vocabulary, never an
     * exception message. The sentence a person reads is assembled here from
     * copy this file owns, so there is no path by which a thrown string becomes
     * display text.
     */
    public static function unavailable(string $control, string $why = ''): self
    {
        $trailer = $why === '' ? '' : ' '.$why;

        return new self(
            $control,
            PostureState::Unverified,
            null,
            'This could not be checked, so nothing is claimed about it.'.$trailer,
        );
    }
}
