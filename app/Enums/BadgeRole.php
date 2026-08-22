<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The badge palette from the shared UI and UX layout template (section 5).
 *
 * A closed set, deliberately. The template's status-badge palette is ENFORCED:
 * a new colour cannot be invented for a new state, so every domain status has
 * to resolve to one of these six. Modelling the palette as an enum rather than
 * as loose CSS class strings means a status that has nowhere sensible to land
 * fails at the type level instead of shipping a badge nobody styled.
 *
 * The backing value is the modifier suffix, so `cssClass()` never has to guess.
 */
enum BadgeRole: string
{
    case Neutral = 'neutral';
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Danger = 'danger';
    case Violet = 'violet';

    /**
     * The class list to render on a badge element.
     *
     * Neutral is the base `.badge` appearance and carries no modifier, matching
     * the stylesheet where the neutral pair is the default rather than a variant.
     */
    public function cssClass(): string
    {
        return $this === self::Neutral ? 'badge' : 'badge badge-'.$this->value;
    }
}
