<?php

declare(strict_types=1);

namespace App\Modules\Platform\Enums;

/**
 * The declared type of a system setting. Feature ADM-021.
 *
 * `system_settings.value` is one text column, so the type is what turns the
 * stored string back into a usable value. It is declared in the catalogue in
 * `config/platform.php` - reviewed code - rather than stored beside the value,
 * because a type an administrator can edit is not a type.
 */
enum SettingType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Choice = 'choice';

    /**
     * An IANA time zone identifier.
     *
     * Stored and cast exactly like `Text`; what the type adds is HOW IT IS
     * OFFERED. It renders as a grouped select built from PHP's own tzdata at
     * request time, so an administrator picks "Singapore (UTC+08:00)" instead
     * of typing "Asia/Singapore" and discovering their typo later.
     *
     * Not a `Choice`, because a Choice carries a static list in the catalogue
     * and this list belongs to PHP - a copied one would be wrong the next time
     * a country changed its rules.
     */
    case TimeZone = 'time_zone';

    /**
     * Turn a stored string into the declared type.
     *
     * A null or unparseable value returns null so the caller falls back to the
     * catalogue default. Returning a zero or an empty string instead would make
     * "never set" indistinguishable from "deliberately set to nothing".
     */
    public function cast(?string $stored): string|int|bool|null
    {
        if ($stored === null) {
            return null;
        }

        return match ($this) {
            self::Text, self::Choice, self::TimeZone => $stored,
            self::Integer => is_numeric($stored) ? (int) $stored : null,
            /* Only the two canonical forms this class writes are accepted.
             * Loose truthiness would read the string "false" as true. */
            self::Boolean => match ($stored) {
                '1', 'true' => true,
                '0', 'false' => false,
                default => null,
            },
        };
    }

    /** Turn a typed value into the string the column holds. */
    public function toStorage(string|int|bool $value): string
    {
        return match ($this) {
            self::Boolean => $value ? '1' : '0',
            default => (string) $value,
        };
    }
}
