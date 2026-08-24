<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * Whether a retention policy has been signed off. Feature PDPA-03.
 *
 * Two states, and the distinction carries more weight than it looks.
 *
 * A DRAFT is somebody's proposal about how long data should be kept. An
 * APPROVED policy is the organisation's position, which is what a regulator
 * would be shown.
 *
 * NEITHER STATE DELETES ANYTHING. Gate 4 stores retention policy and executes
 * none of it (SEC-DEC-038). An approved policy means a human agreed the period;
 * it does not mean anything enforces it, and the screens say so rather than
 * letting a green badge imply protection that does not exist.
 */
enum RetentionStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Approved => 'Approved',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'badge badge-warning',
            self::Approved => 'badge badge-success',
        };
    }
}
