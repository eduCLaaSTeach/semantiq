<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Support;

/**
 * Why no decision was possible. D-93.
 *
 * A FIXED VOCABULARY, never a message. These values reach a security event, and
 * D-12's contract is that nothing free-text ever does.
 */
enum SupersededReason: string
{
    case ObjectEnded = 'object_ended';
    case SubjectRoleEnded = 'subject_role_ended';
    case CompositionChanged = 'composition_changed';

    public function label(): string
    {
        return match ($this) {
            self::ObjectEnded => 'This access had already been removed by another route.',
            self::SubjectRoleEnded => 'The role this access hangs from had already been removed.',
            self::CompositionChanged => 'This access was changed after the review was raised, so it is no longer what was put up for review.',
        };
    }
}
