<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

/**
 * The masking rule - D-27 - in exactly one place.
 *
 * The directory and application identifiers are not secrets. They are also not
 * nothing: together they name the exact Entra application to attack. Masked by
 * default is enough to confirm "yes, that is our tenant" against a value the
 * administrator already holds, without putting the whole identifier into every
 * screenshot that ends up in a support ticket.
 *
 * Short values are NOT partially revealed. Showing 4 of 10 characters discloses
 * far more than 12 of 36, and a rule that quietly behaves differently at
 * different lengths is the kind nobody re-reads.
 *
 * This is never applied to the client secret. There is no code path that could:
 * SecretPresence has no value to pass in.
 */
final class IdentitySafeValue
{
    private const KEEP_LEADING = 8;

    private const KEEP_TRAILING = 4;

    private const MASKABLE_FROM = 16;

    public static function masked(?string $value): string
    {
        $value = (string) $value;

        if ($value === '') {
            return 'Not set';
        }

        if (mb_strlen($value) < self::MASKABLE_FROM) {
            return str_repeat('•', 8);
        }

        return mb_substr($value, 0, self::KEEP_LEADING)
            .'…'
            .mb_substr($value, -self::KEEP_TRAILING);
    }
}
