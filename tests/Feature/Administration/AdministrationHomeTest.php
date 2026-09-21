<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Domains\Projection\DomainSummaryProjection;
use App\Modules\Identity\Health\IdentityHealthCheck;
use App\Modules\People\Projection\PeopleSummaryProjection;
use App\Modules\Platform\Health\HealthInspector;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Platform\Setup\Identity\IdentityConfigurationSource;
use App\Modules\Platform\Setup\IntegrationConfigurationWriter;
use App\Modules\Platform\Setup\IntegrationFamily;
use App\Modules\Platform\Setup\Models\PlatformSetting;
use App\Modules\Platform\Setup\Secrets\IntegrationSecretStore;
use App\Modules\Platform\Setup\Support\SetupProjection;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\SystemHealth\Checks\BackgroundWorkCheck;
use App\Modules\SystemHealth\Checks\CacheStoreCheck;
use App\Modules\SystemHealth\Checks\SessionStoreCheck;
use App\Modules\SystemHealth\Report\SystemHealthReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * P1-11 ADMINISTRATION HOME - the behavioural half.
 *
 * N1 to N15 of the DESIGN's negative and security matrix, plus the two guards
 * the Product Owner's corrections added: G16 (a platform-only source is NOT
 * EVALUATED for a viewer who may not receive it) and G17 (a null organisation
 * never becomes a zero or a "Not configured").
 */
final class AdministrationHomeTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function userWith(?RoleCode $role, bool $withOrganisation = true): User
    {
        $organisation = $withOrganisation ? $this->make->organisation() : null;
        $user = $this->make->user($organisation);

        if ($role !== null) {
            $this->access->assignment($user, $role, $organisation);
        }

        return $user;
    }

    /** @return array<string, array<string, mixed>> keyed by tile key */
    private function tiles(TestResponse $response): array
    {
        $tiles = [];

        foreach ($response->viewData('page')['props']['areas'] as $area) {
            foreach ($area['tiles'] as $tile) {
                $tiles[$tile['key']] = $tile;
            }
        }

        $this->assertCount(8, $tiles, 'The screen did not render all eight tiles.');

        return $tiles;
    }

    /** @return list<array<string, mixed>> */
    private function actions(TestResponse $response): array
    {
        return $response->viewData('page')['props']['actions'];
    }

    // -----------------------------------------------------------------------
    // Who may open it
    // -----------------------------------------------------------------------

    /** N1. No session, no screen. */
    public function test_a_signed_out_visitor_is_sent_to_sign_in(): void
    {
        $this->get('/console/administration')->assertRedirect();
    }

    /** N2 and N3. Both administrator kinds get 200. D-132. */
    public function test_both_administrator_kinds_reach_the_screen(): void
    {
        foreach ([RoleCode::SystemAdministrator, RoleCode::OrganisationAdministrator] as $role) {
            $this->actingAsUser($this->userWith($role))
                ->get('/console/administration')
                ->assertOk();
        }
    }

    /**
     * N2. EVERYTHING ELSE FAILS CLOSED, including the roles that legitimately
     * open other console screens.
     *
     * An Auditor holds EvidenceRead and reaches all four Security Status
     * screens, so this is not "they have no console authority" - it is that
     * OrgAdmin is a different class and this route declares it.
     */
    public function test_every_other_role_is_refused(): void
    {
        foreach ([RoleCode::Auditor, RoleCode::Executive, RoleCode::Manager, RoleCode::BusinessUser, RoleCode::DomainOwner, null] as $role) {
            $this->actingAsUser($this->userWith($role))
                ->get('/console/administration')
                ->assertRedirect(route('auth.access-denied'));
        }
    }

    /** N5. An inactive person holding the role is refused. */
    public function test_an_inactive_administrator_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation, status: UserStatus::Inactive);
        $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

        $this->actingAsUser($user)
            ->get('/console/administration')
            ->assertRedirect(route('auth.account-inactive'));
    }

    // -----------------------------------------------------------------------
    // DESIGN CORRECTION 3 - G16. Authorise BEFORE evaluating.
    // -----------------------------------------------------------------------

    /**
     * G16 / N14. AN ORGANISATION ADMINISTRATOR CAUSES ZERO CALLS TO EITHER
     * PLATFORM-ONLY SOURCE.
     *
     * "Rendered Withheld" is satisfied by a dozen implementations. "Called zero
     * times" is satisfied by one.
     *
     * THE SYSTEM ADMINISTRATOR COUNT IS ASSERTED IN THE SAME TEST, and that is
     * what makes the zero mean anything: a spy that was never wired records
     * zero for every viewer, and the assertion would pass while checking
     * nothing.
     *
     * Mutation: evaluate the source and withhold the result afterwards - the
     * exact shape §4.8 forbids. The counts become 1 and 1, this fails, AND THE
     * RENDERED PAGE IS IDENTICAL - which is why the assertion is on the call
     * count rather than on the output.
     */
    public function test_an_organisation_administrator_evaluates_neither_platform_only_source(): void
    {
        $counts = $this->callCountsFor(RoleCode::OrganisationAdministrator);

        $this->assertSame(
            ['setup' => 0, 'health' => 0],
            $counts,
            'An Organisation Administrator evaluated a platform-only source and then withheld the '
            .'result. A value that is never produced cannot leak; one that is produced and '
            .'discarded is one careless array spread away from the props.'
        );
    }

    /** G16, the half that proves the spy is wired. */
    public function test_a_system_administrator_evaluates_each_platform_only_source_exactly_once(): void
    {
        $this->assertSame(
            ['setup' => 1, 'health' => 1],
            $this->callCountsFor(RoleCode::SystemAdministrator),
            'Either the spy is not wired - in which case the zero-call assertion proves nothing - '
            .'or a source consumed by one tile was evaluated more than once.'
        );
    }

    /**
     * THE SPY COUNTS CONSTRUCTION, NOT METHOD CALLS, and that is the stronger
     * assertion rather than a convenience.
     *
     * Both classes are FINAL, so neither can be subclassed into a recording
     * double - and that is fortunate, because it forced the claim to be made
     * about the OBJECT rather than the method. A SetupProjection that is built
     * and then not asked still exists, with its secret store and its identity
     * resolver behind it, on a request that may not receive a word of what it
     * holds. Zero here means it was never brought into being.
     *
     * The real instance is returned, so nothing about the rendered page
     * changes: the only thing this binding adds is the count.
     *
     * @return array{setup: int, health: int}
     */
    private function callCountsFor(RoleCode $role): array
    {
        $counts = ['setup' => 0, 'health' => 0];

        $this->app->bind(SetupProjection::class, function ($app) use (&$counts): SetupProjection {
            $counts['setup']++;

            return new SetupProjection(
                $app->make(IntegrationSecretStore::class),
                $app->make(IdentityConfigurationSource::class),
            );
        });

        $this->app->bind(SystemHealthReport::class, function ($app) use (&$counts): SystemHealthReport {
            $counts['health']++;

            return new SystemHealthReport(
                $app->make(HealthInspector::class),
                $app->make(IdentityHealthCheck::class),
                $app->make(SessionStoreCheck::class),
                $app->make(CacheStoreCheck::class),
                $app->make(BackgroundWorkCheck::class),
                $app->make(AuditChainVerifier::class),
            );
        });

        $this->actingAsUser($this->userWith($role))->get('/console/administration')->assertOk();

        return $counts;
    }

    /**
     * The other half of D-145: no link the viewer cannot open.
     *
     * An Organisation Administrator gets Withheld on both platform tiles, with
     * NO href and NO number.
     */
    public function test_the_platform_tiles_are_withheld_with_no_link_and_no_number(): void
    {
        $tiles = $this->tiles(
            $this->actingAsUser($this->userWith(RoleCode::OrganisationAdministrator))
                ->get('/console/administration')
        );

        foreach (['integrations', 'health'] as $key) {
            $this->assertSame('withheld', $tiles[$key]['state']);
            $this->assertNull($tiles[$key]['href'], "The withheld [{$key}] tile carried a link.");
            $this->assertSame([], $tiles[$key]['metrics'], "The withheld [{$key}] tile carried a count.");
            $this->assertSame([], $tiles[$key]['rows']);
            $this->assertNull($tiles[$key]['badge']);
        }
    }

    // -----------------------------------------------------------------------
    // DESIGN CORRECTION 2 - G17. The empty deployment.
    // -----------------------------------------------------------------------

    /**
     * N13 / G17. A NULL ORGANISATION NEVER BECOMES `0` OR `Not configured`.
     *
     * D-144 as corrected: the ORGANISATION tile says Not configured, because
     * that is what a missing company profile genuinely means, and the Action
     * Queue leads with setting it up. EVERY OTHER TILE KEEPS ITS OWN STATE -
     * People and Domains read Withheld with no number at all.
     *
     * Mutation: have the empty-deployment path substitute
     * PeopleSummary::of(0, 0, 0), or give the Domains tile a Not configured
     * reading because the organisation is missing.
     */
    public function test_an_empty_deployment_leads_with_organisation_and_rewrites_no_other_tile(): void
    {
        $user = $this->make->user(null);
        $this->access->assignment($user, RoleCode::SystemAdministrator, null);

        $response = $this->actingAsUser($user)->get('/console/administration');
        $response->assertOk();

        $tiles = $this->tiles($response);

        // The one tile that genuinely knows the answer.
        $this->assertSame('Not configured', $tiles['organisation']['badge']['words']);

        // ...and the queue leads with it.
        $actions = $this->actions($response);
        $this->assertNotSame([], $actions, 'An unconfigured deployment produced no action at all.');
        $this->assertSame('organisation', $actions[0]['key']);
        $this->assertStringContainsString('Set up the Organisation', $actions[0]['sentence']);

        // EVERY OTHER TILE KEEPS ITS OWN STATE.
        foreach (['people', 'domains'] as $key) {
            $this->assertSame(
                'withheld',
                $tiles[$key]['state'],
                "[{$key}] was rewritten because the organisation is missing. A source that was "
                .'never asked did not answer zero.'
            );

            $this->assertSame([], $tiles[$key]['metrics'], "[{$key}] carried a count with no scope to count in.");

            $this->assertNull(
                $tiles[$key]['badge'],
                "[{$key}] was given a readiness word by the empty-deployment path rather than by "
                .'its own source.'
            );
        }

        // And no tile anywhere claims a number.
        foreach ($tiles as $key => $tile) {
            if ($tile['state'] !== 'valued') {
                $this->assertSame([], $tile['metrics'], "A non-valued [{$key}] tile carried metrics.");
            }
        }
    }

    // -----------------------------------------------------------------------
    // What the tiles say
    // -----------------------------------------------------------------------

    /** N11. `0` is a real answer and is NOT an action. D-180. */
    public function test_zero_groups_is_a_neutral_count_and_never_an_action(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))
            ->get('/console/administration');

        $tiles = $this->tiles($response);

        $this->assertSame('valued', $tiles['people']['state']);
        $this->assertSame(
            ['label' => 'Active groups', 'count' => 0],
            $tiles['people']['metrics'][2],
        );

        // ...and the people tile has no readiness word at all - it is factual
        // status, not a score.
        $this->assertNull($tiles['people']['badge']);

        foreach ($this->actions($response) as $action) {
            $this->assertNotSame('people', $action['key'], '0 groups produced an action.');
        }
    }

    /**
     * N10. AN ENABLED DOMAIN WITH NO OWNER IS Needs attention AND ONE ROW.
     *
     * A DISABLED unowned domain is in the same fixture and must produce
     * neither, so the exclusion is exercised rather than described.
     */
    public function test_an_unowned_enabled_domain_needs_attention_and_raises_one_row(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

        $this->access->domain($organisation, 'finance', 'Finance');
        $this->access->domain($organisation, 'legacy', 'Legacy', status: 'disabled');

        $response = $this->actingAsUser($user)->get('/console/administration');

        $tiles = $this->tiles($response);

        $this->assertSame('Needs attention', $tiles['domains']['badge']['words']);
        $this->assertSame(['label' => 'Switched on', 'count' => 1], $tiles['domains']['metrics'][0]);
        $this->assertSame(['label' => 'Without an owner', 'count' => 1], $tiles['domains']['metrics'][1]);

        $keys = array_column($this->actions($response), 'key');
        $this->assertContains('domains', $keys);
    }

    /** No enabled domain at all is Not configured, and still not an action. */
    public function test_no_enabled_domains_reads_not_configured_and_raises_no_row(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

        $response = $this->actingAsUser($user)->get('/console/administration');

        $this->assertSame('Not configured', $this->tiles($response)['domains']['badge']['words']);
        $this->assertNotContains('domains', array_column($this->actions($response), 'key'));
    }

    // -----------------------------------------------------------------------
    // The Action Queue's integration rule - D-178, and the one P1-10 spent a
    // whole gate round getting right
    // -----------------------------------------------------------------------

    /**
     * A REQUIRED INTEGRATION THAT IS NOT SET UP IS A ROW.
     *
     * Microsoft sign-in is the only required family - SetupProjection::REQUIRED
     * - and a deployment that has not configured it has genuinely not finished
     * setting up.
     *
     * THIS CASE WAS ADDED BECAUSE A MUTATION SURVIVED. Deleting the
     * required-and-not-configured branch from the queue changed no test: the
     * BEHAVIOUR was right and nothing asserted it, which is the second failure
     * shape CLAUDE.md names - a rule that holds today and would be removed
     * tomorrow without a single red test.
     *
     * Mutation: delete the branch.
     */
    public function test_a_required_integration_that_is_not_configured_raises_one_row(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))
            ->get('/console/administration');

        $identity = $this->tiles($response)['integrations']['rows'][0];

        // The premise, established rather than assumed.
        $this->assertTrue($identity['required'], 'Microsoft sign-in is not marked required.');
        $this->assertSame('not_configured', $identity['status']);

        $this->assertContains(
            'integrations',
            array_column($this->actions($response), 'key'),
            'Microsoft sign-in is required and not configured, and the queue said nothing.'
        );
    }

    /**
     * AN OPTIONAL INTEGRATION THAT IS SIMPLY NOT CONFIGURED IS NOT A ROW.
     *
     * The other half, and the half P1-10 spent a gate round on: a deployment
     * that does not use Microsoft Fabric has not forgotten anything, and Not
     * configured means exactly that. A queue that asked people to configure
     * three optional services is a queue they learn to ignore.
     *
     * Mutation: drop the `required` condition so any not_configured family
     * raises a row.
     */
    public function test_optional_integrations_that_are_not_configured_raise_no_row(): void
    {
        $this->givenMicrosoftSignInIsConfigured();

        $response = $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))
            ->get('/console/administration');

        $rows = $this->tiles($response)['integrations']['rows'];

        // The premise: sign-in is now configured AND the three optional
        // families are not. Without this the absence below would be true
        // because there was nothing to be wrong about.
        $this->assertNotSame('not_configured', $rows[0]['status'], 'Sign-in is still not configured.');

        $optionalAndUnconfigured = array_filter(
            array_slice($rows, 1),
            static fn (array $row): bool => $row['required'] === false && $row['status'] === 'not_configured',
        );

        $this->assertCount(3, $optionalAndUnconfigured, 'The three optional families are not all unconfigured.');

        $this->assertNotContains(
            'integrations',
            array_column($this->actions($response), 'key'),
            'An optional integration that is simply Not configured produced an action. That is '
            .'the correct state of a deployment that does not use it.'
        );
    }

    /** A deployment already signing people in with Microsoft, reading the store. */
    private function givenMicrosoftSignInIsConfigured(): void
    {
        app(IntegrationConfigurationWriter::class)->save(
            IntegrationFamily::Identity,
            [
                'tenant_id' => '22222222-2222-2222-2222-222222222222',
                'client_id' => 'the-application-id',
                'redirect_uri' => 'http://localhost/auth/microsoft/callback',
            ],
            ['client_secret' => 'a-secret-nothing-renders'],
        );

        $settings = PlatformSetting::forUpdate();
        $settings->identity_source = PlatformSetting::SOURCE_STORE;
        $settings->save();

        app(IdentityConfigurationSource::class)->forget();
    }

    // -----------------------------------------------------------------------
    // Source-failure isolation - N7, N8, G13
    // -----------------------------------------------------------------------

    /**
     * G13. A FAILED SOURCE READS Not available, NEVER `0` AND NEVER A POSITIVE
     * STATE - and it takes nothing else down with it.
     *
     * Each source is forced to throw in turn. The tile it owns must be
     * unavailable, every OTHER tile must still be valued, the page must be 200,
     * and the failed source must contribute NO action row.
     *
     * Mutation: return a zeroed summary from the catch. The tile reads valued
     * with three zeroes, and this fails.
     */
    public function test_each_source_can_fail_alone_without_becoming_a_zero(): void
    {
        $cases = [
            'people' => PeopleSummaryProjection::class,
            'domains' => DomainSummaryProjection::class,
            'integrations' => SetupProjection::class,
            'health' => SystemHealthReport::class,
            'posture' => PostureEvaluator::class,
        ];

        foreach ($cases as $tileKey => $class) {
            $this->refreshApplication();
            $this->setUpTheDatabaseForAFreshApplication();

            $this->app->bind($class, function () use ($class): object {
                throw new RuntimeException("[{$class}] is deliberately unavailable.");
            });

            $organisation = $this->make->organisation();
            $user = $this->make->user($organisation);
            $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

            $response = $this->actingAsUser($user)->get('/console/administration');

            $response->assertOk();

            $tiles = $this->tiles($response);

            $this->assertSame(
                'unavailable',
                $tiles[$tileKey]['state'],
                "[{$tileKey}] did not report Not available when its own source threw."
            );

            $this->assertSame([], $tiles[$tileKey]['metrics'], "[{$tileKey}] failed and still carried a number.");
            $this->assertNull($tiles[$tileKey]['badge'], "[{$tileKey}] failed and still carried a status.");

            $this->assertNotContains(
                $tileKey,
                array_column($this->actions($response), 'key'),
                "[{$tileKey}] could not answer and still produced an action. An unanswerable "
                .'question is not an action.'
            );

            // ...and the rest of the page is untouched. Posture and exceptions
            // share one evaluation, so they fail together and are exempted from
            // each other's check.
            $together = $tileKey === 'posture' ? ['posture', 'exceptions'] : [$tileKey];

            foreach ($tiles as $key => $tile) {
                if (in_array($key, $together, true)) {
                    continue;
                }

                $this->assertNotSame(
                    'unavailable',
                    $tile['state'],
                    "[{$key}] was taken down by a failure in [{$tileKey}]."
                );
            }
        }
    }

    private function setUpTheDatabaseForAFreshApplication(): void
    {
        $this->artisan('migrate:fresh');

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
    }

    // -----------------------------------------------------------------------
    // N9. What reaches the browser
    // -----------------------------------------------------------------------

    /**
     * N9. NO PERSON, NO DOMAIN RECORD, NO SECRET, NO CREDENTIAL.
     *
     * THE PREMISE IS ESTABLISHED FIRST: a person with a distinctive name and
     * email, and a business domain with a distinctive name, both exist and are
     * both inside the scope this screen counts. A page that leaked them WOULD
     * contain these strings, so their absence is a finding rather than an
     * accident of an empty database.
     */
    public function test_no_person_domain_or_credential_reaches_the_page(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::SystemAdministrator, $organisation);

        /*
         * The person's numeric ID is deliberately NOT in the list below. A
         * single-digit id appears in every count on the page, so asserting its
         * absence would fail on "2 active people" and say nothing about a leak.
         * The identifiers that WOULD only appear through a leak are asserted
         * instead: the name, the email, the mailbox domain, the external
         * subject and the field names a record would arrive under.
         */
        User::query()->create([
            'organisation_id' => $organisation->id,
            'provider' => 'microsoft',
            'external_subject' => 'subject-leak-check',
            'tenant_id' => '11111111-1111-1111-1111-111111111111',
            'email' => 'marion.delacroix@leak-check.test',
            'display_name' => 'Marion Delacroix',
            'status' => UserStatus::Active,
        ]);

        $this->access->domain($organisation, 'zanzibar', 'Zanzibar Logistics');

        // The premise: this person IS counted, so the page is genuinely about
        // a scope that contains them.
        $response = $this->actingAsUser($user)->get('/console/administration');
        $this->assertSame(2, $this->tiles($response)['people']['metrics'][0]['count']);

        $body = $response->getContent() ?: '';

        foreach ([
            'Marion Delacroix',
            'marion.delacroix',
            'leak-check.test',
            'Zanzibar Logistics',
            'zanzibar',
            'subject-leak-check',
            'client_secret',
            'tenant_id',
            'external_subject',
        ] as $mustNotAppear) {
            $this->assertStringNotContainsString(
                $mustNotAppear,
                $body,
                "[{$mustNotAppear}] reached the Administration Home page source."
            );
        }
    }

    /** D-131. /console is unchanged and is still not this screen. */
    public function test_the_console_landing_page_is_unchanged(): void
    {
        $response = $this->actingAsUser($this->userWith(RoleCode::SystemAdministrator))->get('/console');

        $response->assertOk();

        $this->assertSame('Console/Home', $response->viewData('page')['component']);
    }

    /**
     * Empty is a real state and is rendered as one.
     *
     * A deployment with an organisation, no domains, no exceptions and no
     * overdue reviews has genuinely nothing to do, and the screen says so
     * rather than showing an empty box.
     */
    public function test_a_deployment_with_nothing_to_do_says_so(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::OrganisationAdministrator, $organisation);

        $response = $this->actingAsUser($user)->get('/console/administration');

        $this->assertSame(
            [],
            $this->actions($response),
            'A deployment with nothing outstanding produced an action anyway.'
        );

        /*
         * THE EMPTY-STATE COPY IS ASSERTED AGAINST THE COMPONENT, not against
         * this response. There is no JavaScript test runner in this repository
         * - no CI test renders the DOM - so the server sends props and the
         * sentence lives in the React source. Asserting it here would have
         * passed only if the words had been put in the payload, which is the
         * wrong place for them.
         */
        $this->assertStringContainsString(
            'Nothing needs your attention.',
            (string) file_get_contents(base_path('resources/js/Pages/Administration/Home.jsx')),
            'The screen has no empty state for the Action Queue, so a deployment with nothing to '
            .'do would render an empty box a reader cannot tell apart from a failure.'
        );
    }
}
