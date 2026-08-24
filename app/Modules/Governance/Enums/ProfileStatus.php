<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * Where a governance profile version stands. Features ADM-014 and ADM-015.
 *
 * Decision D4, approved 24 August 2026, recorded as SEC-DEC-065: profiles are
 * VERSIONED and a version becomes immutable once approved. These three states
 * are the whole lifecycle, and a row is in exactly one of them.
 *
 *   Draft       Being written. Editable. Binds nothing, and nothing downstream
 *               may treat it as a position the organisation has taken.
 *   Approved    A person approved it. Immutable from that moment. At most one
 *               approved version exists per organisation per profile type.
 *   Superseded  It was approved once and a later version replaced it. Kept, and
 *               kept readable, because the point of versioning is being able to
 *               answer what was in force in March.
 *
 * THE STATE THAT MATTERS MOST IS THE ABSENT ONE. No approved version at all is
 * not a fourth case here - it is the absence of a row, and the screens report
 * it as Not Configured. A draft must never be presented as though it were
 * approved (SEC-DEC-068), which is the reason `isInForce()` exists rather than
 * call sites comparing statuses themselves.
 */
enum ProfileStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
            self::Superseded => 'Superseded',
        };
    }

    /**
     * Whether this version is the organisation's actual position.
     *
     * The one question every downstream reader should ask. A draft answers
     * false however complete it looks, which is what stops a seeded draft
     * (D12) from being mistaken for a decision somebody made.
     */
    public function isInForce(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Whether this version may still be edited.
     *
     * Enforced in the service and on the model as well, not only here. This
     * method exists so a screen can hide a form; the refusal itself lives
     * where a console command or a future API would also meet it.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    /**
     * The design system's badge class. The whole class, not a fragment.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge badge-warning',
            self::Approved => 'badge badge-success',
            /* The bare class IS the neutral variant. */
            self::Superseded => 'badge',
        };
    }

    /**
     * One sentence a screen can show beside the badge.
     */
    public function explanation(): string
    {
        return match ($this) {
            self::Draft => 'This is a draft. It records what somebody has written down, not a position '
                .'the organisation has taken, and nothing else in SemantIQ acts on it.',
            self::Approved => 'This is the version in force. It cannot be edited - changing it creates a '
                .'new version and supersedes this one.',
            self::Superseded => 'A later version replaced this one. It is kept so that what was in force '
                .'at an earlier date can still be read.',
        };
    }
}
