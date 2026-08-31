<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Support;

use RuntimeException;

/**
 * A refused structural change.
 *
 * The reason is a stable machine key, never a sentence assembled from model
 * internals. The message is safe to show an administrator; nothing here carries
 * a stack trace, a framework internal, or structure the viewer may not see.
 */
final class StructureViolation extends RuntimeException
{
    private function __construct(
        public readonly string $reason,
        string $message,
        /** @var list<string> */
        public readonly array $blockedBy = [],
    ) {
        parent::__construct($message);
    }

    public static function because(string $reason, string $message): self
    {
        return new self($reason, $message);
    }

    /**
     * @param  list<string>  $names
     */
    public static function blockedByChildren(string $reason, string $message, array $names): self
    {
        return new self($reason, $message, $names);
    }
}
