<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Security\Posture\Adapters\SourceAdapter;
use App\Modules\Security\Posture\Evidence;
use RuntimeException;

/**
 * An adapter that answers exactly what a test tells it to - or throws.
 *
 * THROWING IS THE POINT of half its use. "A source that throws yields Unverified
 * AND THE ROW STILL RENDERS" cannot be proven by watching a working adapter, and
 * the tempting wrong implementation - catch and continue - removes the row
 * entirely, which makes the screen lie by omission.
 *
 * The exception message is deliberately something a leak test can search for.
 */
final class StubAdapter implements SourceAdapter
{
    public const SECRET_IN_THE_EXCEPTION = 'SQLSTATE[HY000] connection failed for user semantiq_prod';

    /**
     * @param  list<string>  $answers
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        private readonly array $answers,
        private readonly array $evidence = [],
        private readonly bool $throws = false,
    ) {}

    public function answers(): array
    {
        return $this->answers;
    }

    public function evidence(): array
    {
        if ($this->throws) {
            throw new RuntimeException(self::SECRET_IN_THE_EXCEPTION);
        }

        return $this->evidence;
    }
}
