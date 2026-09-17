<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * The four kinds of Exception, because the four are answered by completely
 * different people.
 *
 * Declared beside each control in the catalogue rather than derived from its
 * state: "the live probe has never run" and "the last administrator has gone"
 * are both non-healthy, and telling an administrator to treat them the same way
 * is how an exceptions screen becomes wallpaper.
 */
enum ExceptionKind: string
{
    case Unresolved = 'unresolved';
    case AcceptedLimitation = 'accepted_limitation';
    case VerificationIncomplete = 'verification_incomplete';
    case NotYetConfigured = 'not_yet_configured';

    public function label(): string
    {
        return match ($this) {
            self::Unresolved => 'Unresolved security condition',
            self::AcceptedLimitation => 'Accepted limitation',
            self::VerificationIncomplete => 'Verification incomplete',
            self::NotYetConfigured => 'Not yet configured',
        };
    }

    public function whoResolves(): string
    {
        return match ($this) {
            self::Unresolved => 'An administrator, today.',
            self::AcceptedLimitation => 'Nobody - this is documented and accepted.',
            self::VerificationIncomplete => 'An administrator can often produce the evidence.',
            self::NotYetConfigured => 'An administrator, once.',
        };
    }
}
