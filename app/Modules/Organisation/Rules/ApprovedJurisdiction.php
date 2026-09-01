<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Rules;

use App\Modules\Organisation\Support\Jurisdictions;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A jurisdiction must be one of the approved ISO 3166-1 territories.
 *
 * The screen offers a dropdown; this is the reason a dropdown is safe. A select
 * element constrains nothing — the value arrives in an HTTP request and anyone
 * can send any string. The list is enforced HERE, on the server, against the
 * same packaged data the dropdown renders from, so the two cannot disagree.
 *
 * Empty is allowed. Jurisdiction is nullable and a legal entity whose
 * jurisdiction is not yet known is a real state, not an error.
 */
final class ApprovedJurisdiction implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && $value !== null) {
            $fail('Select a jurisdiction from the list.');

            return;
        }

        if (! Jurisdictions::permits($value)) {
            $fail('Select a jurisdiction from the list.');
        }
    }
}
