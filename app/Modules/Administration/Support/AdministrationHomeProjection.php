<?php

declare(strict_types=1);

namespace App\Modules\Administration\Support;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Support\ActionClass;
use App\Modules\Domains\Projection\DomainSummaryProjection;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\People\Projection\PeopleSummaryProjection;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Setup\Support\SetupProjection;
use App\Modules\Reviews\Projection\ReviewSummaryProjection;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Projection\PostureProjection;
use App\Modules\Security\Projection\Viewer;
use App\Modules\SystemHealth\Report\SystemHealthReport;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * ADMINISTRATION HOME COMPOSES. IT DOES NOT QUERY.
 *
 * Every fact on this screen belongs to a unit that already shipped it. This
 * class asks each of them one question, re-decides nothing they have already
 * decided, and derives only what D-130 gave it: the three-way Business Domain
 * reading and the Action Queue.
 *
 * THERE IS NO ELOQUENT IMPORT IN THIS FILE - no Builder, no DB facade, no
 * model but the viewer every authorising path already names.
 * AdministrationHomeIsAProjectionTest asserts that as a property of the file
 * rather than as an intention, because a rule in a docblock lasts until the
 * next person is in a hurry, and this project proved that twice in one unit.
 *
 * ---------------------------------------------------------------------------
 * AUTHORISE BEFORE EVALUATING A PLATFORM-ONLY SOURCE - DESIGN CORRECTION 3
 * ---------------------------------------------------------------------------
 *
 * Two sources are platform-only: Platform Integrations (SetupProjection::all)
 * and System Health (SystemHealthReport::areas). For a viewer who may not
 * receive their values, THEY ARE NOT CALLED AT ALL.
 *
 * The earlier shape evaluated them and then declined to render the result.
 * Three things are wrong with that, and only the third is about tidiness:
 *
 *   - a value that is never produced cannot leak. Withholding AFTER evaluation
 *     puts a platform-wide answer in a local variable on a request that may not
 *     receive it, one careless array spread away from the props;
 *   - it charges the viewer who gains nothing by it. An Organisation
 *     Administrator would otherwise pay three configuration reads and a full
 *     local health inspection to be told "Withheld";
 *   - "rendered Withheld" is satisfied by a dozen implementations. "Called
 *     ZERO times" is satisfied by one, and that is what the spy guard asserts.
 *
 * THE AUTHORISATION QUESTION IS THE EXISTING ONE. seesPlatformValues() asks
 * the same AccessEngine decision RequireActionClass makes for PlatformAdmin -
 * the class System Health and Integrations are themselves protected by. This
 * unit does not invent a second definition of "may see platform values", so a
 * future change to that rule moves this screen with it.
 *
 * ---------------------------------------------------------------------------
 * WHY THE SIX SOURCES ARE RESOLVED FROM THE CONTAINER AND NOT INJECTED
 * ---------------------------------------------------------------------------
 *
 * CONSTRUCTOR INJECTION WOULD HAVE BUILT THEM ALL, FOR EVERY VIEWER. Laravel
 * resolves a constructor argument before the body runs, so a SetupProjection
 * would exist - with its secret store and its identity resolver behind it - on
 * an Organisation Administrator's request that is not allowed to receive a word
 * of what it holds. "Not called" would then be true of the METHOD and false of
 * the OBJECT, which is a weaker claim than the one §4.8 asks for.
 *
 * IT ALSO MAKES THE ISOLATION RULE COVER CONSTRUCTION. A source that fails to
 * BUILD - a missing binding, a constructor that throws - is caught by the same
 * try/catch as one that fails to answer, so it renders Not available like any
 * other failure instead of returning 500 for the whole page. With constructor
 * injection that failure happens before this class exists and there is nothing
 * to catch it with.
 *
 * OrganisationService AND AccessEngine STAY INJECTED, because neither is
 * optional: the engine decides who this viewer is, and the organisation is the
 * scope three other sources are asked in. If the database is unreachable the
 * request has already failed in the session middleware, so there is no partial
 * page to render.
 *
 * ---------------------------------------------------------------------------
 * A MISSING SCOPE NARROWS, AND A FAILED SOURCE IS NEVER A ZERO
 * ---------------------------------------------------------------------------
 *
 * D-144 AS CORRECTED: a genuinely unconfigured deployment says so on the
 * ORGANISATION tile and the Action Queue leads with setting it up. Every other
 * tile keeps its own authoritative state. Missing organisation scope is NEVER
 * rewritten as `Not configured` or `0` for a source that did not say so -
 * People and Domains read Withheld, because there is no scope to count within
 * and "0 users" would be an answer nobody gave.
 *
 * Each source is caught INDIVIDUALLY at this boundary. One failing renders its
 * own tile as Not available and contributes no Action Queue row; the others are
 * unaffected. The DIRECTION of that rule matters more than the rule: a
 * dashboard rendering "0 open exceptions" because the evaluator threw is worse
 * than one rendering nothing, because it is confidently wrong about security,
 * and wrong in the reassuring direction.
 *
 * NO CACHE - D-141. A stale summary is the failure this unit is most exposed
 * to, and this screen's answer is "right now".
 *
 * NO WRITE, AND NO SECURITY EVENT - D-160's catalogue stays at fifteen keys.
 * Rendering a summary records nothing.
 */
final class AdministrationHomeProjection
{
    public function __construct(
        private readonly Container $container,
        private readonly AccessEngine $engine,
        private readonly OrganisationService $organisations,
    ) {}

    /**
     * The whole screen, in one pass.
     *
     * @return array<string, mixed>
     */
    public function for(User $viewer): array
    {
        $seesPlatformValues = $this->seesPlatformValues($viewer);

        /*
         * THE SCOPE EVERY OTHER CONSOLE SCREEN USES. People, Groups, Business
         * Domains and Access Reviews are all scoped by the organisation
         * RequireOrganisation resolves, which is OrganisationService::current().
         * D-133 keeps that middleware off this route - a day-one deployment
         * must not be bounced to Company Profile by the screen that exists to
         * tell it what to do next - so the scope is resolved here instead, from
         * the same source, and the dashboard cannot disagree with the screens
         * it summarises.
         */
        $organisation = $this->organisations->current();
        $organisationId = $organisation?->id;

        $tiles = [
            'organisation' => $this->organisationTile($organisation !== null),
            'people' => $this->peopleTile($viewer, $organisationId),
            'domains' => $this->domainsTile($viewer, $organisationId),
            'integrations' => $this->integrationsTile($seesPlatformValues),
            'reviews' => $this->reviewsTile($viewer, $organisationId),
            'health' => $this->healthTile($seesPlatformValues),
        ];

        // ONE EVALUATION, TWO TILES. ViewerReport's own docblock says
        // exceptions() is "the single source of the list, the tab count and the
        // heading count"; evaluating twice would be two answers to one question
        // and the screen would eventually show both.
        [$tiles['posture'], $tiles['exceptions']] = $this->securityTiles($viewer);

        return [
            'areas' => [
                $this->area(
                    'readiness',
                    'Readiness',
                    'Whether this deployment has been set up.',
                    [$tiles['organisation'], $tiles['people'], $tiles['domains'], $tiles['integrations']],
                ),
                $this->area(
                    'security',
                    'Security',
                    'What the security controls report right now.',
                    [$tiles['posture'], $tiles['exceptions']],
                ),
                $this->area(
                    'operations',
                    'Reviews & Operations',
                    'Outstanding review work, and whether the service is running.',
                    [$tiles['reviews'], $tiles['health']],
                ),
            ],
            'actions' => array_map(
                static fn (ActionRow $row): array => $row->toArray(),
                $this->actionQueue($tiles),
            ),
        ];
    }

    /**
     * MAY THIS VIEWER RECEIVE A PLATFORM-WIDE VALUE?
     *
     * Asked of the engine, with the action class the two platform screens are
     * themselves protected by. Not a role check written here, and not a copy of
     * P1-06's Viewer rule: one definition, in the place that already owns it.
     */
    private function seesPlatformValues(User $viewer): bool
    {
        return $this->engine->decide(AccessQuestion::administration(
            $viewer,
            'administration.home',
            ActionClass::PlatformAdmin,
        ))->allowed;
    }

    /**
     * ORGANISATION - D-179. Configured, or not, and nothing else.
     *
     * NO STRUCTURE COUNTS. Legal entities, business units, departments and
     * teams are not mandatory for a complete Organisation state and are not
     * shown here as counts; they stay on the Organisation feature.
     */
    private function organisationTile(bool $configured): Tile
    {
        $reading = $configured ? Readiness::Configured : Readiness::NotConfigured;

        return new Tile(
            key: 'organisation',
            name: 'Organisation',
            state: TileState::Valued,
            note: $configured
                ? 'The company profile has been created. Structure is managed on the Organisation screen.'
                : 'No company profile has been created yet. This is the first thing to set up.',
            badge: self::readinessBadge($reading),
            href: '/console/organisation',
            linkLabel: 'Open Organisation',
        );
    }

    /**
     * USERS & GROUPS - D-180. THREE NEUTRAL COUNTS AND NO VERDICT.
     *
     * A deployment with no groups is not a deployment with a problem, so there
     * is no readiness word here and no Action Queue row below. The numbers are
     * facts; nothing on this tile interprets them.
     */
    private function peopleTile(User $viewer, ?int $organisationId): Tile
    {
        try {
            $summary = $this->container->get(PeopleSummaryProjection::class)->for($viewer, $organisationId);
        } catch (Throwable) {
            return Tile::unavailable('people', 'Users & Groups');
        }

        if (! $summary->valued) {
            return Tile::withheld(
                'people',
                'Users & Groups',
                'There is no organisation to count people within yet, so no figures are shown.',
            );
        }

        return new Tile(
            key: 'people',
            name: 'Users & Groups',
            state: TileState::Valued,
            note: 'Who has an account here, and the groups they are organised into. An account grants nothing on its own.',
            metrics: [
                ['label' => 'Active people', 'count' => (int) $summary->activeUsers],
                ['label' => 'Inactive people', 'count' => (int) $summary->inactiveUsers],
                ['label' => 'Active groups', 'count' => (int) $summary->activeGroups],
            ],
            href: '/console/people/users',
            linkLabel: 'Open Users & Groups',
        );
    }

    /**
     * BUSINESS DOMAINS - D-181. THE FACTS ARE P1-04'S; THE READING IS THIS
     * UNIT'S, and D-130 is what makes that so.
     *
     *   no enabled domains          Not configured - nothing has been switched on
     *   an enabled domain unowned   Needs attention - somebody should be accountable
     *   otherwise                   Ready
     *
     * A DISABLED DOMAIN WITHOUT AN OWNER IS NOT A GAP, and the seam already
     * excludes it: a domain the organisation switched off needs nobody
     * accountable for it, and counting it would fill the queue with work that
     * does not exist.
     */
    private function domainsTile(User $viewer, ?int $organisationId): Tile
    {
        try {
            $summary = $this->container->get(DomainSummaryProjection::class)->for($viewer, $organisationId);
        } catch (Throwable) {
            return Tile::unavailable('domains', 'Business Domains');
        }

        if (! $summary->valued) {
            return Tile::withheld(
                'domains',
                'Business Domains',
                'There is no organisation to read business domains for yet, so no figures are shown.',
            );
        }

        $enabled = (int) $summary->enabled;
        $unowned = (int) $summary->enabledUnowned;

        $reading = match (true) {
            $enabled === 0 => Readiness::NotConfigured,
            $unowned > 0 => Readiness::NeedsAttention,
            default => Readiness::Ready,
        };

        return new Tile(
            key: 'domains',
            name: 'Business Domains',
            state: TileState::Valued,
            note: match ($reading) {
                Readiness::NotConfigured => 'No business domains have been switched on yet.',
                Readiness::NeedsAttention => 'Some business domains that are switched on have nobody accountable for them.',
                default => 'Every business domain that is switched on has somebody accountable for it.',
            },
            badge: self::readinessBadge($reading),
            metrics: [
                ['label' => 'Switched on', 'count' => $enabled],
                ['label' => 'Without an owner', 'count' => $unowned],
            ],
            href: '/console/domains',
            linkLabel: 'Open Business Domains',
        );
    }

    /**
     * PLATFORM INTEGRATIONS - D-178, and DESIGN CORRECTION 3.
     *
     * NOT EVALUATED AT ALL for a viewer who may not receive it. The guard is
     * the CONDITION of the call, not a filter after it.
     *
     * FOUR ROWS, NOT ONE COLLAPSED VERDICT. D-178 says this tile derives
     * nothing - the status IS the status - and P1-09 already refused an
     * area-level roll-up for the reason that applies here too: "one Not
     * applicable and one Unavailable" has no summary that is not a lie.
     *
     * IT READS SetupProjection AND NOTHING ELSE - not config(), not
     * PlatformSetting, not a second resolver. Identity has two possible
     * authorities and P1-10 shipped a defect by asking the wrong one; this
     * screen asks the one the sign-in path asks, by asking nobody itself.
     *
     * `fields`, `choices` AND `secrets` ARE PRESENT IN THE ARRAY AND ARE NOT
     * READ. Nothing below names them, so nothing can forward them. `required`
     * IS read, because the Action Queue's rule turns on it: a REQUIRED family
     * that is not configured is a real gap, and an OPTIONAL one that is not
     * configured is the correct state of a deployment that does not use it.
     */
    private function integrationsTile(bool $seesPlatformValues): Tile
    {
        if (! $seesPlatformValues) {
            return Tile::withheld(
                'integrations',
                'Platform Integrations',
                'These are deployment-wide credentials rather than one organisation\'s, so they are not shown here.',
            );
        }

        try {
            $families = $this->container->get(SetupProjection::class)->all();
        } catch (Throwable) {
            return Tile::unavailable('integrations', 'Platform Integrations');
        }

        return new Tile(
            key: 'integrations',
            name: 'Platform Integrations',
            state: TileState::Valued,
            note: 'The outside services this deployment uses. Only Microsoft sign-in is required.',
            rows: array_map(
                static fn (array $family): array => [
                    'name' => (string) $family['name'],
                    'status' => (string) $family['status'],
                    'statusInWords' => (string) $family['statusInWords'],
                    'required' => (bool) $family['required'],
                ],
                $families,
            ),
            href: '/console/integrations',
            linkLabel: 'Open Integrations',
        );
    }

    /**
     * SECURITY POSTURE AND OPEN EXCEPTIONS - ONE EVALUATION, TWO TILES.
     *
     * P1-06 decides what this viewer may value; `seesPlatformValues` on the
     * ViewerReport already carries that and is not re-decided here. The
     * aggregate, its label, the caption and the exception count all come from
     * the one report, so the two tiles cannot disagree with each other or with
     * Security Status.
     *
     * @return array{0: Tile, 1: Tile}
     */
    private function securityTiles(User $viewer): array
    {
        try {
            $report = PostureProjection::for(
                $this->container->get(PostureEvaluator::class)->evaluate(),
                Viewer::for($viewer, $this->engine),
            );
        } catch (Throwable) {
            return [
                Tile::unavailable('posture', 'Security posture'),
                Tile::unavailable('exceptions', 'Open exceptions'),
            ];
        }

        $exceptions = count($report->exceptions());

        return [
            new Tile(
                key: 'posture',
                name: 'Security posture',
                state: TileState::Valued,
                note: $report->caption(),
                badge: [
                    'kind' => 'posture',
                    'tone' => $report->aggregate()->value,
                    'words' => $report->aggregateLabel(),
                ],
                href: '/console/security',
                linkLabel: 'Open Security Status',
            ),
            /*
             * "Security exceptions", NOT "Open exceptions" - a browser-sweep
             * finding, and the same defect P1-09 already fixed once.
             *
             * The tile used to be called "Open exceptions" and carried a link
             * reading "Open exceptions" three lines below it: the same two
             * words meaning the ADJECTIVE in the heading and the VERB in the
             * link. P1-09 found the identical shape - an area named "Background
             * work" holding a row named "Background work" - and the lesson is
             * the same. The COUNT is still labelled "Open exceptions", because
             * that is what the number is.
             */
            new Tile(
                key: 'exceptions',
                name: 'Security exceptions',
                state: TileState::Valued,
                note: $exceptions === 0
                    ? 'Nothing is currently outside the secure baseline.'
                    : 'Controls that are not currently meeting the secure baseline.',
                metrics: [['label' => 'Open exceptions', 'count' => $exceptions]],
                href: '/console/security/exceptions',
                // The tab this lands on, in the words that screen calls it.
                linkLabel: 'Open Exceptions',
            ),
        ];
    }

    /**
     * ACCESS REVIEWS. VISIBILITY IS DECIDED ENTIRELY BY P1-07.
     *
     * The seam asks ReviewerAuthority::scopeVisible(), which is already
     * organisation-scoped even for a platform-scoped System Administrator, so
     * this tile inherits that rule rather than re-deciding it.
     */
    private function reviewsTile(User $viewer, ?int $organisationId): Tile
    {
        try {
            $summary = $this->container->get(ReviewSummaryProjection::class)->for($viewer, $organisationId);
        } catch (Throwable) {
            return Tile::unavailable('reviews', 'Access Reviews');
        }

        if (! $summary->valued) {
            return Tile::withheld(
                'reviews',
                'Access Reviews',
                'There is no organisation to read review work for yet, so no figures are shown.',
            );
        }

        $overdue = (int) $summary->overdue;

        return new Tile(
            key: 'reviews',
            name: 'Access Reviews',
            state: TileState::Valued,
            note: $overdue === 0
                ? 'Nothing is past its due date.'
                : 'Some reviews are past their due date.',
            metrics: [
                ['label' => 'Awaiting a decision', 'count' => (int) $summary->outstanding],
                ['label' => 'Past their due date', 'count' => $overdue],
            ],
            href: '/console/access-reviews',
            linkLabel: 'Open Access Reviews',
        );
    }

    /**
     * SYSTEM HEALTH - DESIGN CORRECTION 3, and one deliberate departure from
     * the DESIGN that is recorded rather than quietly made.
     *
     * NOT EVALUATED AT ALL for a viewer who may not receive it, and with no
     * link: System Health is PlatformAdmin, and D-145 forbids rendering a
     * destination the viewer cannot open.
     *
     * THE DESIGN SAID "collapsed to one overall state". THIS TILE DOES NOT
     * COLLAPSE. SystemHealthArea's own docblock refuses an area-level verdict
     * because it "would be a SEVENTH status nothing measured, computed from
     * rows whose meanings do not combine"; rolling fourteen rows across five
     * areas into one badge is the same mistake one level up. So this reports
     * two NEUTRAL COUNTS instead - what needs attention, and what nobody has
     * checked - which are smaller claims and true ones.
     *
     * NOT CHECKED IS SHOWN, NOT HIDDEN. It is the whole reason HealthStatus is
     * six long rather than five, and a tile that reported only failures would
     * let "nobody has looked" read as "everything is fine".
     *
     * ZERO NETWORK CALLS, STRUCTURALLY. SystemHealthReport::areas() is built on
     * inspectLocal() and storedReport(). It never reaches inspect(), report(),
     * EntraDiscovery, a connection tester or semantiq:health - P1-09 built the
     * local projection precisely so a screen could promise to contact nobody
     * and mean it.
     */
    private function healthTile(bool $seesPlatformValues): Tile
    {
        if (! $seesPlatformValues) {
            return Tile::withheld(
                'health',
                'System Health',
                'Service health is platform information rather than one organisation\'s, so it is not shown here.',
            );
        }

        try {
            $areas = $this->container->get(SystemHealthReport::class)->areas();
        } catch (Throwable) {
            return Tile::unavailable('health', 'System Health');
        }

        $attention = 0;
        $unchecked = 0;

        foreach ($areas as $area) {
            foreach ($area->rows as $row) {
                // The status words, never a status this file assigned. A row
                // that is Not configured or Not applicable is neither a failure
                // nor an unasked question, and is counted as neither.
                match ($row->status->value) {
                    'degraded', 'unavailable' => $attention++,
                    'not_checked' => $unchecked++,
                    default => null,
                };
            }
        }

        return new Tile(
            key: 'health',
            name: 'System Health',
            state: TileState::Valued,
            note: $attention === 0
                ? 'No check is currently reporting a problem.'
                : 'Some checks are reporting a problem. System Health shows which.',
            metrics: [
                ['label' => 'Needing attention', 'count' => $attention],
                ['label' => 'Not checked yet', 'count' => $unchecked],
            ],
            href: '/console/system-health',
            linkLabel: 'Open System Health',
        );
    }

    /**
     * THE ACTION QUEUE. It owns no task - D-135 - and issues no query.
     *
     * Every row is derived from a tile that was already built above, so a
     * source that was never evaluated cannot produce one: the condition is not
     * suppressed, it is never asked. A source that FAILED contributes no row
     * either - an unanswerable question is not an action.
     *
     * NEVER A ROW FOR:
     *
     *   - 0 users, 0 groups, 0 domains. D-180 says so in as many words;
     *   - an optional integration that is simply Not configured. That is the
     *     correct state of a deployment that does not use it, and P1-10 spent a
     *     whole gate round making Not configured mean exactly that;
     *   - a carried gate. A tile may show a gate's state; showing it closes
     *     nothing, and an action queue cannot ask anybody to close one.
     *
     * ORGANISATION LEADS, AND THAT IS THE ONLY ORDERING RULE - D-144 as
     * corrected. It says "do this first". It does not say the other tiles are
     * unknown, and it must never be implemented by making them say so.
     *
     * @param  array<string, Tile>  $tiles
     * @return list<ActionRow>
     */
    private function actionQueue(array $tiles): array
    {
        $rows = [];

        if (($tiles['organisation']->badge['tone'] ?? null) === Readiness::NotConfigured->tone()) {
            $rows[] = new ActionRow(
                'organisation',
                'Set up the Organisation. Nothing else can be organised until the company profile exists.',
                '/console/organisation',
                'Organisation',
            );
        }

        if ($this->countOn($tiles['domains'], 'Without an owner') > 0) {
            $rows[] = new ActionRow(
                'domains',
                'Some business domains that are switched on have nobody accountable for them.',
                '/console/domains',
                'Business Domains',
            );
        }

        if ($this->countOn($tiles['exceptions'], 'Open exceptions') > 0) {
            $rows[] = new ActionRow(
                'exceptions',
                'Open security exceptions need review.',
                '/console/security/exceptions',
                // The tab it LANDS on. The row used to say "Security Status"
                // and open the Exceptions tab - a destination name that named
                // somewhere else.
                'Exceptions',
            );
        }

        if ($this->countOn($tiles['reviews'], 'Past their due date') > 0) {
            $rows[] = new ActionRow(
                'reviews',
                'Access reviews are past their due date.',
                '/console/access-reviews/overdue',
                'Access Reviews',
            );
        }

        if ($this->countOn($tiles['health'], 'Needing attention') > 0) {
            $rows[] = new ActionRow(
                'health',
                'A service check is reporting a problem.',
                '/console/system-health',
                'System Health',
            );
        }

        if ($this->integrationNeedsAttention($tiles['integrations'])) {
            $rows[] = new ActionRow(
                'integrations',
                'An integration needs attention.',
                '/console/integrations',
                'Integrations',
            );
        }

        return $rows;
    }

    /**
     * A metric from a tile, or 0 - AND THE 0 IS NOT A COUNT.
     *
     * A withheld or unavailable tile carries no metrics at all, so this returns
     * zero and NO ROW IS DERIVED. That is the whole point: the absence of a
     * number produces the absence of an action, never an action based on a
     * number nobody gave. Nothing renders this value.
     */
    private function countOn(Tile $tile, string $label): int
    {
        foreach ($tile->metrics as $metric) {
            if ($metric['label'] === $label) {
                return $metric['count'];
            }
        }

        return 0;
    }

    /**
     * A REQUIRED INTEGRATION THAT IS NOT SET UP, OR ANY INTEGRATION REPORTING A
     * PROBLEM.
     *
     * An OPTIONAL integration reading Not configured is deliberately not a row.
     * A deployment that does not use Microsoft Fabric has not forgotten
     * anything, and a queue that says otherwise is a queue people learn to
     * ignore.
     *
     * `required` COMES FROM SetupProjection, not from a list repeated here.
     * Today exactly one family is required; a second would move this row
     * without anything having to notice.
     *
     * A WITHHELD OR FAILED TILE HAS NO ROWS AT ALL, so this returns false and
     * no row is derived. The condition is not suppressed - it is never asked.
     */
    private function integrationNeedsAttention(Tile $tile): bool
    {
        foreach ($tile->rows as $row) {
            if (in_array($row['status'], ['degraded', 'unavailable'], true)) {
                return true;
            }

            if ($row['required'] && $row['status'] === 'not_configured') {
                return true;
            }
        }

        return false;
    }

    /**
     * A readiness reading, wearing the shared status pill.
     *
     * ONE PLACE. Two tiles show a readiness word and both build the badge
     * here, so neither can drift into its own tone mapping.
     *
     * @return array{kind: string, tone: string, words: string}
     */
    private static function readinessBadge(Readiness $reading): array
    {
        return [
            'kind' => 'readiness',
            'tone' => $reading->tone(),
            'words' => $reading->inWords(),
        ];
    }

    /**
     * @param  list<Tile>  $tiles
     * @return array<string, mixed>
     */
    private function area(string $key, string $name, string $description, array $tiles): array
    {
        return [
            'key' => $key,
            'name' => $name,
            'description' => $description,
            'tiles' => array_map(static fn (Tile $tile): array => $tile->toArray(), $tiles),
        ];
    }
}
