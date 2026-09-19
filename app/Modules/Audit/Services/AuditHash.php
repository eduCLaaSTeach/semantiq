<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use DateTimeInterface;

/**
 * THE CHAIN HASH. One declared field order, iterated - never a hand-written
 * concatenation.
 *
 * WHY THE FIELD LIST IS A CONSTANT AND THE HASH IS COMPUTED FROM IT. A column
 * added later and forgotten here would be a field nobody could prove had not
 * been edited - a silent hole in exactly the guarantee this class provides.
 * AuditHashCoverageTest compares this list against the table's own columns, so
 * the hole is a build failure instead.
 *
 * NULL IS A LITERAL \x00, NOT AN EMPTY STRING. Otherwise a row with
 * reason = '' and a row with reason = null hash identically and one can be
 * substituted for the other - the classic way a chain is made forgeable
 * without anybody noticing.
 */
final class AuditHash
{
    /** A byte that cannot occur in any stored value. */
    private const SEPARATOR = "\x1f";

    private const NULL_MARKER = "\x00";

    /**
     * Every hashed column, in the order they are hashed. `previous_hash` is
     * prepended separately and `row_hash` is the output, so neither appears.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'sequence',
        'occurred_at',
        'event',
        'category',
        'actor_type',
        'actor_user_id',
        'actor_subject',
        'actor_tenant',
        'actor_provider',
        'organisation_id',
        'subject_user_id',
        'subject_external',
        'target_type',
        'target_id',
        'outcome',
        'reason',
        'role',
        'domain_id',
        'scope',
        'sensitivity',
        'context_expires_at',
    ];

    /** @param  array<string, mixed>  $row */
    public static function of(string $previousHash, array $row): string
    {
        $parts = [$previousHash];

        foreach (self::FIELDS as $field) {
            $value = $row[$field] ?? null;

            $parts[] = $value === null ? self::NULL_MARKER : self::scalar($value);
        }

        return hash('sha256', implode(self::SEPARATOR, $parts));
    }

    private static function scalar(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            // Nothing hashed here is a date object today - occurred_at and
            // context_expires_at are exact strings precisely so they cannot be
            // rewritten between hashing and storage. This stays for a future
            // column, and uses the same format the strings carry.
            return $value->format('Y-m-d H:i:s.u');
        }

        return is_bool($value) ? ($value ? '1' : '0') : (string) $value;
    }
}
