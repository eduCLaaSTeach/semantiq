<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Security\Catalogue\ControlCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * THE SCREENS: discoverability, the named states, and the events content.
 *
 * P1-05 delivered the Access Simulator and nobody could find it - a capability
 * reachable only by typing a URL is, to a customer, a capability that was not
 * delivered. CLAUDE.md §4 names it exactly: "navigation that exists technically
 * but the user cannot actually discover". The first test below is that lesson
 * applied before the fact.
 */
final class SecurityStatusScreensTest extends TestCase
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

    private function signedInAs(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }

    private function administrator(): User
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);
        $this->access->assignment($user, RoleCode::SystemAdministrator);

        return $user;
    }

    /**
     * SECURITY STATUS IS REACHABLE FROM THE NAVIGATION, not only by URL.
     *
     * The node must be a LEAF with a route - a locked node carries no
     * destination at all, and NavigationNode's constructor already refuses a
     * locked node that has one.
     */
    public function test_security_status_is_a_navigable_node_rather_than_a_locked_one(): void
    {
        $response = $this->signedInAs($this->administrator())->get('/console/security');
        $response->assertOk();

        $areas = $response->viewData('page')['props']['productAreas'];

        $node = null;

        foreach ($areas as $area) {
            foreach ($area['nodes'] ?? [] as $candidate) {
                if (($candidate['label'] ?? null) === 'Security Status') {
                    $node = $candidate;
                }
            }
        }

        $this->assertNotNull($node, 'Security Status is not in the navigation a signed-in administrator receives.');

        $this->assertSame(
            '/console/security',
            $node['route'],
            'The node carries no route, so it renders as a "Soon" row the user cannot click.',
        );

        $this->assertFalse($node['locked'] ?? false);
    }

    /** And the tab strip renders four route-backed links - D-82, no fifth tab. */
    public function test_the_tab_strip_has_exactly_the_four_approved_subscreens(): void
    {
        $source = ScreenSource::rendered('Components/SecurityTabs.jsx');

        foreach ([
            'Secure Baseline',
            'Privileged Access Health',
            'Exceptions',
            'Security Events',
        ] as $tab) {
            $this->assertStringContainsString($tab, $source);
        }

        // Exactly four hrefs, and every one is a real anchor rather than an
        // ARIA tab widget - Pattern B, the shared standard.
        $this->assertSame(4, substr_count($source, "href: '/console/security"));
        $this->assertStringContainsString('<a', $source);
        $this->assertStringNotContainsString('role="tab"', $source);

        // D-82: no fifth tab for domains.
        $this->assertStringNotContainsString("Business Domains', href", $source);
    }

    /**
     * THE DANGEROUS EMPTY STATE. "No exceptions" must never read "All clear".
     *
     * It is the screen most likely to be mistaken for an all-clear, so it
     * carries the counts - including how many controls nobody has verified.
     */
    public function test_the_no_exceptions_state_states_the_counts_rather_than_all_clear(): void
    {
        $response = $this->signedInAs($this->administrator())->get('/console/security/exceptions');
        $response->assertOk();

        $caption = $response->viewData('page')['props']['summary']['caption'];

        $this->assertStringContainsString('applicable controls reported', $caption);
        $this->assertStringContainsString('not verified', $caption);

        $source = ScreenSource::rendered('Pages/Security/Exceptions.jsx');

        foreach (['All clear', 'Everything looks good', 'Nothing to do'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $source);
        }
    }

    /** The events screen shows every declared event, as readable labels. */
    public function test_the_events_screen_lists_every_declared_event_in_business_language(): void
    {
        $response = $this->signedInAs($this->administrator())->get('/console/security/events');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(count(SecurityEventLogger::events()), $props['total']);

        $listed = 0;

        foreach ($props['groups'] as $group) {
            $listed += count($group['events']);
        }

        $this->assertSame(
            count(SecurityEventLogger::events()),
            $listed,
            'The screen lists fewer events than the product records, so its coverage claim is false.',
        );

        $this->assertCount(15, $props['permittedFields']);
    }

    /**
     * N-SS33 AT THE SURFACE. No raw dotted identifier reaches a rendered prop.
     *
     * A screen that printed access.step_up.refused at an administrator would be
     * exactly the CLAUDE.md §4 failure - an internal key on a user-facing
     * surface.
     */
    public function test_no_internal_identifier_reaches_a_rendered_surface(): void
    {
        $user = $this->administrator();

        foreach ([
            '/console/security',
            '/console/security/privileged-access',
            '/console/security/exceptions',
            '/console/security/events',
        ] as $screen) {
            $props = $this->signedInAs($user)->get($screen)->viewData('page')['props'];

            $this->walk($props, $screen);
        }
    }

    /**
     * Every STRING in a rendered prop tree, checked for an internal identifier.
     *
     * Control identifiers like B-1 and PR-4 are permitted - they are stable
     * references a person can quote, not internal keys - and they are checked
     * separately below to make sure they are never the ONLY thing shown.
     */
    private function walk(mixed $value, string $screen, string $path = ''): void
    {
        if (is_array($value)) {
            foreach ($value as $key => $child) {
                $this->walk($child, $screen, $path.'.'.$key);
            }

            return;
        }

        if (! is_string($value)) {
            return;
        }

        // A URL legitimately contains slashes and dots; a control id is
        // permitted. What must never appear is a dotted snake_case identifier.
        if (str_starts_with($value, '/') || str_starts_with($value, 'http')) {
            return;
        }

        $this->assertDoesNotMatchRegularExpression(
            '/^[a-z_]+(\.[a-z_]+)+$/',
            $value,
            "{$screen} renders the internal identifier \"{$value}\" at {$path}.",
        );

        foreach (SecurityEventLogger::events() as $event) {
            $this->assertStringNotContainsString(
                $event,
                $value,
                "{$screen} renders the raw event key \"{$event}\" at {$path}.",
            );
        }
    }

    /** A control identifier is never shown without the words that explain it. */
    public function test_every_row_carries_a_label_and_not_only_an_identifier(): void
    {
        $rows = $this->signedInAs($this->administrator())
            ->get('/console/security')
            ->viewData('page')['props']['rows'];

        $this->assertNotSame([], $rows);

        foreach ($rows as $row) {
            $this->assertNotSame('', $row['label']);
            $this->assertNotSame($row['control'], $row['label']);

            // A label reads as English, not as an enum value.
            $this->assertDoesNotMatchRegularExpression('/^[a-z_]+$/', $row['label']);
        }
    }

    /**
     * A DEPLOYMENT WITH NOTHING CONFIGURED renders its controls honestly rather
     * than showing an empty screen or a green one.
     */
    public function test_a_deployment_with_nothing_configured_still_reports_every_control(): void
    {
        $response = $this->signedInAs($this->administrator())->get('/console/security');
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertNotSame([], $props['rows']);

        $controls = array_map(static fn (array $r): string => $r['control'], $props['rows']);

        foreach ([
            ControlCatalogue::IDENTITY_TRUST,
            ControlCatalogue::STEP_UP_EXTERNAL,
            ControlCatalogue::ENCRYPTION,
            ControlCatalogue::SSO_RECHECK,
        ] as $expected) {
            $this->assertContains($expected, $controls);
        }

        $this->assertNotSame(
            'healthy',
            $props['summary']['aggregate'],
            'A deployment with no identity configuration and an unrun probe reported healthy.',
        );
    }

    /**
     * The carried P1-02 gate reads as NOT VERIFIED, in the Product Owner's
     * words, and is never healthy.
     */
    public function test_the_carried_sso_recheck_reads_as_not_verified(): void
    {
        $rows = $this->signedInAs($this->administrator())
            ->get('/console/security')
            ->viewData('page')['props']['rows'];

        $gate = null;

        foreach ($rows as $row) {
            if ($row['control'] === ControlCatalogue::SSO_RECHECK) {
                $gate = $row;
            }
        }

        $this->assertNotNull($gate);
        $this->assertSame('unverified', $gate['state']);
        $this->assertSame('Not verified', $gate['stateLabel']);

        $this->assertStringContainsString('second permanent System Administrator', $gate['finding']);
        $this->assertStringContainsString('Nothing is known to be wrong', $gate['finding']);
    }

    /** Remediation is NAVIGATION: every owner link points at an existing screen. */
    public function test_every_remediation_link_points_at_a_real_screen(): void
    {
        $rows = $this->signedInAs($this->administrator())
            ->get('/console/security')
            ->viewData('page')['props']['rows'];

        $linked = 0;

        foreach ($rows as $row) {
            if ($row['ownerHref'] === null) {
                continue;
            }

            $linked++;

            $this->signedInAs($this->administrator())
                ->get($row['ownerHref'])
                ->assertSuccessful();

            $this->assertNotSame('', $row['ownerLabel']);
        }

        $this->assertGreaterThan(0, $linked, 'No row offers anywhere to go.');
    }
}
