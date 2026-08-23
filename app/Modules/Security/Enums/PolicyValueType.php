<?php

declare(strict_types=1);

namespace App\Modules\Security\Enums;

/**
 * The declared type of a security policy value. Features ADM-009 to ADM-011.
 *
 * `security_policies.value` is one text column, so the type is what turns the
 * stored string back into something usable. It is declared in
 * `config/security.php` - reviewed code - not stored beside the value, for the
 * same reason `SettingType` is: a type an administrator can edit is not a type.
 *
 * Deliberately a separate enum from `Platform\Enums\SettingType` rather than a
 * shared one. The two catalogues have different guards and different audit
 * rules, and a shared type would be the thread by which one becomes coupled to
 * the other. `TextList` exists only here, because only a security policy needs
 * an allow-list of email domains.
 */
enum PolicyValueType: string
{
    case Text = 'text';
    case Integer = 'integer';
    case Boolean = 'boolean';
    case Choice = 'choice';

    /**
     * A newline-separated list, stored as one string.
     *
     * Used for the allowed email domain list. Stored rather than normalised
     * into a table because the list is short, is read as a whole, and has no
     * identity of its own - a `security_policy_email_domains` table would be
     * three joins for something that is one field on one screen.
     */
    case TextList = 'text_list';

    /**
     * Turn a stored string into the declared type.
     *
     * Null or unparseable returns null so the caller falls back to the
     * catalogue default: "never set" must stay distinguishable from
     * "deliberately set to nothing".
     */
    public function cast(?string $stored): string|int|bool|null
    {
        if ($stored === null) {
            return null;
        }

        return match ($this) {
            self::Text, self::Choice, self::TextList => $stored,
            self::Integer => is_numeric($stored) ? (int) $stored : null,
            /* Only the two canonical forms this application writes. Loose
             * truthiness would read the string "false" as true, and on a
             * security policy that is the difference between a control being on
             * and being off. */
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

    /**
     * Split a stored list into its entries.
     *
     * Blank lines and surrounding whitespace are dropped, so a trailing newline
     * does not become an empty allow-list entry that matches everything.
     *
     * @return list<string>
     */
    public static function listEntries(?string $stored): array
    {
        if ($stored === null || trim($stored) === '') {
            return [];
        }

        return array_values(array_filter(
            array_map(trim(...), preg_split('/[\r\n,]+/', $stored) ?: []),
            static fn (string $entry): bool => $entry !== '',
        ));
    }
}
