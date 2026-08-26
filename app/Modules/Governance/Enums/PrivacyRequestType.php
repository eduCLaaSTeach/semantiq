<?php

declare(strict_types=1);

namespace App\Modules\Governance\Enums;

/**
 * What the data subject is asking for. Feature PDPA-01.
 *
 * Only `Correction` routes through `AwaitingDecision`: an access request is
 * answered by disclosing, a correction request is answered by deciding.
 */
enum PrivacyRequestType: string
{
    case Access = 'access';
    case Correction = 'correction';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Access => 'Access',
            self::Correction => 'Correction',
            self::Withdrawal => 'Withdrawal of consent',
        };
    }

    public function explanation(): string
    {
        return match ($this) {
            self::Access => 'Asking what personal data is held about them.',
            self::Correction => 'Asserting that something held about them is wrong.',
            self::Withdrawal => 'Withdrawing consent for a stated use of their data.',
        };
    }

    /**
     * Whether this type needs a decision step before a response.
     */
    public function needsDecision(): bool
    {
        return $this !== self::Access;
    }
}
