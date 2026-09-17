<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

use App\Modules\Security\Posture\ExceptionKind;
use App\Modules\Security\Posture\PostureState;

/**
 * A VALUED row - one this viewer may see the answer to.
 */
final class ViewerRow
{
    public function __construct(
        public readonly string $control,
        public readonly string $label,
        public readonly PostureState $state,
        public readonly ExceptionKind $kind,
        public readonly string $finding,
        public readonly ?string $ownerHref,
        public readonly ?string $ownerLabel,

        /** The domain a per-domain row belongs to. Null for deployment controls. */
        public readonly ?string $qualifier = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'control' => $this->control,
            'label' => $this->label,
            'state' => $this->state->value,
            'stateLabel' => $this->state->label(),
            'kind' => $this->kind->label(),
            'finding' => $this->finding,
            'ownerHref' => $this->ownerHref,
            'ownerLabel' => $this->ownerLabel,
            'qualifier' => $this->qualifier,
            'withheld' => false,
        ];
    }
}
