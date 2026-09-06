<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Platform\Security\SecurityEventLogger;

/**
 * A SecurityEventLogger that remembers what it was asked to record.
 *
 * WHY NOT Log::spy(). Mockery's `shouldHaveReceived('info')->with(...)` reports
 * its expectation as `info(<Any Arguments>)` - the argument constraint is not
 * applied - so an assertion written that way passes whenever ANY info line was
 * logged. It looked like it was checking which event fired and was checking
 * that logging happened at all, which is true in every case the engine denies.
 *
 * Found by mutation: the operational signal was swapped for an ordinary event
 * and the assertion still passed.
 *
 * This records instead. It still runs the real validation - an unknown event or
 * a forbidden context key throws exactly as it would in production - so a test
 * using it cannot pass against an event nobody declared.
 */
final class RecordingSecurityEventLogger extends SecurityEventLogger
{
    /** @var list<array{event: string, context: array<string, scalar|null>}> */
    public array $recorded = [];

    public function record(string $event, array $context = []): void
    {
        // The real validation first, so a leak is still unrepresentable.
        parent::record($event, $context);

        $this->recorded[] = ['event' => $event, 'context' => $context];
    }

    /**
     * Named recordedEvents() rather than events(), because the parent already
     * has a STATIC events() listing the declared set - and overriding a static
     * with an instance method is a fatal error, not a subtle one.
     *
     * @return list<string>
     */
    public function recordedEvents(): array
    {
        return array_column($this->recorded, 'event');
    }

    /** @return list<array<string, scalar|null>> */
    public function contextsFor(string $event): array
    {
        return array_values(array_map(
            static fn (array $entry): array => $entry['context'],
            array_filter($this->recorded, static fn (array $entry): bool => $entry['event'] === $event),
        ));
    }
}
