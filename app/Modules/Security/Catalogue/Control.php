<?php

declare(strict_types=1);

namespace App\Modules\Security\Catalogue;

use App\Modules\Security\Posture\ControlKind;
use App\Modules\Security\Posture\ControlScope;
use App\Modules\Security\Posture\ExceptionKind;

/**
 * One catalogued control: everything about it that is NOT a measurement.
 *
 * The identifier, the words a person reads, whether it bears a state or only a
 * count, whether its VALUE is System-Administrator-only, and where it is
 * configured. All fixed here rather than decided at render time, because a
 * value decided at render time is a value two screens can disagree about.
 *
 * ownerRoute is a ROUTE NAME of an existing screen. It is never a URL typed by
 * hand, and it is never an action: remediation is navigation, and the
 * destination re-authorises on arrival through its own RequireActionClass.
 */
final class Control
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly ControlKind $kind,
        public readonly ControlScope $scope,
        public readonly ExceptionKind $exceptionKind,
        public readonly ?string $ownerRoute = null,
        public readonly ?string $ownerLabel = null,
    ) {}
}
