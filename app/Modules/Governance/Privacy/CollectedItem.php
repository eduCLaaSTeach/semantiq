<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy;

use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Enums\DisclosureTreatment;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * One thing found about a data subject, and how it may be disclosed.
 *
 * THE CONSTRUCTOR REFUSES THE COMBINATION THAT LEAKS. A `describe` or
 * `exclude` item may not carry a `detail` payload. That is not a style rule:
 * `detail` is the verbatim structured data, and a described item exists
 * precisely because the verbatim form would name somebody who did not ask for
 * anything. Refusing it here means no collector can produce the leaking shape,
 * however it is later rendered or serialised.
 */
final class CollectedItem
{
    /**
     * @param  array<string, mixed>|null  $detail
     */
    public function __construct(
        public readonly string $sourceTable,
        public readonly DisclosureBand $band,
        public readonly DisclosureTreatment $treatment,
        public readonly string $summary,
        public readonly ?array $detail = null,
        public readonly ?Carbon $occurredAt = null,
    ) {
        if ($this->detail !== null && $this->treatment !== DisclosureTreatment::Include) {
            throw new InvalidArgumentException(
                "A `{$this->treatment->value}` item from `{$this->sourceTable}` carries a detail payload. "
                .'Only `include` items may. A described item exists because the verbatim form would '
                .'disclose somebody who did not ask for anything.'
            );
        }

        if (trim($this->summary) === '') {
            throw new InvalidArgumentException(
                "An item from `{$this->sourceTable}` has an empty summary. Every item must say something "
                .'the subject can read, including one that discloses nothing.'
            );
        }
    }

    /**
     * A described fact: what happened and when, never who else was involved.
     */
    public static function describe(
        string $sourceTable,
        DisclosureBand $band,
        string $summary,
        ?Carbon $occurredAt = null,
    ): self {
        return new self($sourceTable, $band, DisclosureTreatment::Describe, $summary, null, $occurredAt);
    }

    /**
     * A verbatim disclosure. Band A and B only in practice.
     *
     * @param  array<string, mixed>  $detail
     */
    public static function include(
        string $sourceTable,
        DisclosureBand $band,
        string $summary,
        array $detail,
        ?Carbon $occurredAt = null,
    ): self {
        return new self($sourceTable, $band, DisclosureTreatment::Include, $summary, $detail, $occurredAt);
    }

    /**
     * Something considered and deliberately withheld.
     *
     * These rows are kept precisely BECAUSE they disclose nothing. They are the
     * evidence that a table was looked at and a decision was taken, which is
     * what makes the coverage claim checkable rather than asserted.
     */
    public static function withheld(string $sourceTable, DisclosureBand $band, string $summary): self
    {
        return new self($sourceTable, $band, DisclosureTreatment::Exclude, $summary);
    }
}
