<?php

declare(strict_types=1);

namespace App\Modules\Platform\Setup\Support;

use App\Modules\SystemHealth\Report\HealthStatus;

/**
 * WHAT REACT RECEIVES. There is no field here a secret could occupy.
 *
 * D-155 made unrepresentable rather than remembered. `secretConfigured` is a
 * BOOLEAN: it says a secret is set and cannot say what it is. Every other
 * field is a non-secret typed value the administrator entered themselves.
 *
 * THE GUARD IS THE ABSENCE, NOT A STRIPPING STEP. A projection that carried
 * the value and removed it on the way out would be one forgotten branch away
 * from shipping it, and the branch that forgets is always the error path.
 */
final readonly class IntegrationView
{
    /**
     * @param  array<string, scalar|null>  $fields  NON-SECRET typed fields only
     * @param  array<string, bool>  $secrets  name => configured. NEVER a value.
     */
    public function __construct(
        public string $family,
        public string $name,
        public string $status,
        public string $statusInWords,
        public ?string $explanation,
        public array $fields,
        /** @var array<string, array<string, string>> field => (value => words) */
        public array $choices,
        public array $secrets,
        public ?string $lastTestedAt,
        public ?string $lastChangedAt,
        public bool $required,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'name' => $this->name,
            'status' => $this->status,
            'statusInWords' => $this->statusInWords,
            'explanation' => $this->explanation,
            'fields' => $this->fields,
            'choices' => $this->choices,
            'secrets' => $this->secrets,
            'lastTestedAt' => $this->lastTestedAt,
            'lastChangedAt' => $this->lastChangedAt,
            'required' => $this->required,
        ];
    }

    /**
     * The status in the words a person reads. Never the enum value.
     *
     * THESE ARE HealthStatusBadge'S WORDS, EXACTLY.
     *
     * The first draft of this method invented its own - "Working", "Slow to
     * answer", "Not working" - which read perfectly well on their own and
     * meant that the SAME status appeared as "Working" in the setup step list
     * and "Available" in the badge two inches away. One deployment, one
     * status, two vocabularies: a customer would reasonably ask which was
     * right, and there would be no answer.
     *
     * SystemHealthAndIntegrationsShareOneVocabulary asserts the two stay
     * identical, so a future edit to either has to move both.
     */
    public static function statusInWords(HealthStatus $status): string
    {
        return match ($status) {
            HealthStatus::Available => 'Available',
            HealthStatus::Degraded => 'Needs attention',
            HealthStatus::Unavailable => 'Unavailable',
            HealthStatus::NotConfigured => 'Not configured',
            HealthStatus::NotApplicable => 'Not applicable',
            HealthStatus::NotChecked => 'Not checked',
        };
    }
}
