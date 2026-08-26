<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * What was decided about a disputed record. Feature PDPA-01, decision D11.
 *
 * `Noted` is not a failure state and not a holding state. Where the disputed
 * record is an audit event, noting the dispute IS the correct and complete
 * outcome: the trail cannot be edited, so the annotation beside it is the
 * remedy. `Applied` means a correctable record - a `users` row - was actually
 * changed through the normal registry.
 */
enum CorrectionOutcome: string
{
    case Noted = 'noted';
    case Applied = 'applied';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::Noted => 'Recorded beside the entry',
            self::Applied => 'Corrected',
            self::Refused => 'Not corrected',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Noted => 'The audit trail cannot be edited, so this assertion is recorded permanently beside the entry it disputes. Anyone reading the entry sees the dispute.',
            self::Applied => 'The record was corrected through the normal path, so the change is audited like any administrative edit.',
            self::Refused => 'Not corrected, with the reason recorded. Refusal is a lawful outcome.',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::Noted => 'badge-info',
            self::Applied => 'badge-success',
            self::Refused => 'badge-warning',
        };
    }
}
