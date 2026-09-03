<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * What P1-04 writes to the security log, and - more importantly - what it
 * cannot write.
 *
 * SEVEN EVENTS AND NO NEW CONTEXT KEY. That is the D-12 boundary working as
 * designed rather than a coincidence: a domain's name, code and description are
 * business content, the logger has no key for free text, and so a leak here is
 * UNREPRESENTABLE rather than merely discouraged. A design that had needed a new
 * key would have been a design putting business content in the log.
 */
final class DomainSecurityEventTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private DomainFactory $domains;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->domains = new DomainFactory;
    }

    /**
     * N48. ALLOWED_KEYS IS UNCHANGED BY THIS UNIT.
     *
     * The list is read through the logger's own behaviour rather than by
     * reflection on a private constant: a key that is not permitted throws, so
     * this asserts the boundary as it is actually enforced.
     *
     * Mutation: add a 'name' or 'code' key for a domain.
     */
    public function test_the_logger_still_refuses_a_key_that_could_carry_business_content(): void
    {
        $logger = app(SecurityEventLogger::class);

        foreach (['name', 'code', 'description', 'domain', 'label', 'title'] as $forbidden) {
            try {
                $logger->record(SecurityEventLogger::BUSINESS_DOMAIN_CREATED, [$forbidden => 'Finance']);

                $this->fail("The logger accepted a [{$forbidden}] key. Business content can now reach the log.");
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString('not permitted', $exception->getMessage());
            }
        }
    }

    /**
     * N47. No domain event carries a name, a code, a description, an email or
     * any identifier.
     *
     * Asserted against what is ACTUALLY LOGGED during a full round of
     * operations, not against the shape of the call sites.
     *
     * Mutation: add the domain's name to any context array.
     */
    public function test_no_domain_event_carries_business_content(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $lines = [];

        Log::listen(function ($message) use (&$lines): void {
            $lines[] = json_encode([$message->message, $message->context]);
        });

        $domain = $this->domains->domain($organisation, 'Distinctive Name', 'distinctive-code');

        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/enable");
        $this->actingAsUser($admin)->put("/console/domains/{$domain->id}", [
            'name' => 'Distinctive Name',
            'description' => 'A distinctive description',
            'access_expectation' => 'limited',
        ]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/disable");
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner/clear");

        $this->assertNotEmpty($lines, 'No security events were logged, so this proves nothing.');

        $log = implode("\n", $lines);

        foreach ([
            'Distinctive Name', 'distinctive-code', 'A distinctive description',
            $owner->email, $owner->display_name, $owner->external_subject, $admin->email,
        ] as $secret) {
            $this->assertStringNotContainsString(
                (string) $secret,
                $log,
                'Business content or an identifier reached the security log.'
            );
        }
    }

    /**
     * Every operation that changes something records it, and the events are the
     * seven declared ones.
     *
     * Mutation: stop recording an enable; add an eighth event.
     */
    public function test_each_operation_records_its_declared_event(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $owner = $this->make->user($organisation);

        $events = [];

        Log::listen(function ($message) use (&$events): void {
            if (isset($message->context['event'])) {
                $events[] = $message->context['event'];
            } elseif (str_contains($message->message, 'business_domain')) {
                $events[] = $message->message;
            }
        });

        $domain = $this->domains->domain($organisation, 'Finance', 'finance');

        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner", ['user_id' => $owner->id]);
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/enable");
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/disable");
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/owner/clear");

        $recorded = array_values(array_unique(array_filter(
            $events,
            fn (string $event): bool => str_starts_with($event, 'business_domain')
        )));

        sort($recorded);

        $this->assertSame([
            'business_domain.disabled',
            'business_domain.enabled',
            'business_domain.owner.assigned',
            'business_domain.owner.cleared',
        ], $recorded);
    }

    /**
     * REFUSALS ARE DELIBERATELY NOT LOGGED, and this asserts the decision
     * rather than leaving it to be noticed.
     *
     * P1-02's own note says a screen is not logged merely because it is
     * sensitive: volume buries what matters and P1-08 inherits the noise.
     * user.provision.refused exists because a failed provision can indicate
     * enumeration; being told "assign an owner first" cannot indicate anything.
     *
     * Mutation: add a business_domain.*.refused event and record it.
     */
    public function test_a_refusal_records_no_security_event(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $domain = $this->domains->domain($organisation);

        $events = [];

        Log::listen(function ($message) use (&$events): void {
            $events[] = $message->message.json_encode($message->context);
        });

        // Every refusal this unit has.
        $this->actingAsUser($admin)->patch("/console/domains/{$domain->id}/enable");
        $this->actingAsUser($admin)->post('/console/domains', ['name' => 'X', 'code' => 'finance']);
        $this->actingAsUser($admin)->delete("/console/domains/{$domain->id}");

        $fromRefusals = $events;

        foreach ($fromRefusals as $line) {
            $this->assertStringNotContainsString(
                'refused',
                $line,
                'A refusal was logged as a security event. P1-04 records changes, not attempts.'
            );
        }

        /*
         * AND THE LISTENER IS ACTUALLY LISTENING.
         *
         * Three refusals recording nothing is the expected result, so an
         * assertion over an empty array would pass with the listener detached,
         * the route broken, or the whole logger removed. One successful
         * operation on the same listener proves the silence above was a
         * decision rather than an absence.
         */
        $this->actingAsUser($admin)->post('/console/domains', ['name' => 'Real', 'code' => 'real']);

        $this->assertGreaterThan(
            count($fromRefusals),
            count($events),
            'The listener captured nothing even for a successful write, so the silence during the '
            .'refusals above proved nothing at all.'
        );
    }

    /** The declared set is exactly seven, and the family name is one word. */
    public function test_exactly_seven_domain_events_are_declared(): void
    {
        $declared = array_values(array_filter(
            SecurityEventLogger::events(),
            fn (string $event): bool => str_starts_with($event, 'business_domain')
        ));

        sort($declared);

        $this->assertSame([
            'business_domain.created',
            'business_domain.disabled',
            'business_domain.enabled',
            'business_domain.owner.assigned',
            'business_domain.owner.cleared',
            'business_domain.purged',
            'business_domain.updated',
        ], $declared);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
