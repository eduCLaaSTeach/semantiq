<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture;

/**
 * Whether a control's VALUE is legitimately System-Administrator-only.
 *
 * Declared once in the catalogue, beside the control's identifier, and read by
 * the projection. It is never inferred at render time: an inference is a rule
 * two screens can disagree about, and this one decides what a person may see.
 */
enum ControlScope
{
    case Platform;
    case Organisation;
}
