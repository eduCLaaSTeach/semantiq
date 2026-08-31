<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

/**
 * The D-09 seam, and nothing more.
 *
 * P1-00 must create a System Administrator, but the role engine belongs to
 * P1-05 and pre-building it is forbidden. So exactly one value exists here.
 * Adding Organisation Administrator, Executive, Manager, Business User or
 * Auditor to this enum would be building P1-05 early; a test asserts the case
 * count stays at one.
 *
 * P1-05 OWNS REPLACING THIS. It is a seam, not the authorisation engine, and it
 * confers no business-domain access whatsoever.
 */
enum PlatformRole: string
{
    case SystemAdministrator = 'system_administrator';
}
