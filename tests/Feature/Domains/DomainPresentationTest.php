<?php

declare(strict_types=1);

namespace Tests\Feature\Domains;

use App\Modules\Domains\Models\AccessExpectation;
use App\Modules\Domains\Models\DomainKind;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DomainFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ScreenSource;
use Tests\TestCase;

/**
 * What the screens actually say.
 *
 * EVERY SOURCE ASSERTION HERE GOES THROUGH ScreenSource::rendered(), which
 * strips comments first. P1-03 had exactly this kind of assertion pass against
 * a DOCBLOCK that quoted the copy while the cell rendered nothing at all - the
 * M-N13 mutation survived, and the fix was applied to every such assertion
 * rather than only the one that was caught.
 */
final class DomainPresentationTest extends TestCase
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
     * N36. THE SENTENCE THAT CARRIES THE WHOLE UNIT.
     *
     * Naming somebody accountable for Finance reads like giving them Finance to
     * almost every reader. The screen says otherwise, in words, and it is not a
     * comment.
     *
     * Mutation: delete the sentence. Or move it into a comment, which is what
     * makes ScreenSource necessary.
     */
    public function test_the_record_says_the_owner_gets_no_access(): void
    {
        $rendered = ScreenSource::rendered('Pages/Domains/Record.jsx');

        $this->assertStringContainsString(
            'The owner is accountable for this domain. They do not get access to it.',
            $rendered,
            'The record page no longer says that owning a domain grants nothing.'
        );

        $this->assertStringContainsString('Access is assigned in Roles &amp; Access.', $rendered);
    }

    /**
     * N27 and N36. The access expectation says, on screen, that it enforces
     * nothing - and does not dress itself as a control.
     *
     * P1-02 was corrected for the same class of overclaim: it was not permitted
     * to say "Sign-in works" when it had only checked that settings loaded.
     *
     * Mutation: remove the sentence; add a lock icon; use the word "policy".
     */
    public function test_the_access_expectation_says_it_enforces_nothing(): void
    {
        $rendered = ScreenSource::rendered('Pages/Domains/Record.jsx');

        $this->assertStringContainsString(
            'This is a statement of intent. It does not grant or restrict anything today.',
            $rendered,
            'The inert-statement sentence is gone from the record page.'
        );

        foreach (['i-lock', 'i-shield', 'policy', 'Policy', 'enforced', 'Enforced', 'secured'] as $overclaim) {
            $this->assertStringNotContainsString(
                $overclaim,
                $rendered,
                "The record page uses [{$overclaim}], which implies enforcement that does not exist."
            );
        }
    }

    /** Every screen says, at the top, that nothing here grants access. */
    public function test_every_domain_screen_says_it_grants_nothing(): void
    {
        $this->assertStringContainsString(
            'Nothing here grants access to any of it.',
            ScreenSource::rendered('Components/DomainsPage.jsx'),
            'The page header no longer says the unit grants nothing.'
        );

        $this->assertStringContainsString(
            'A domain does not give anybody access to anything.',
            ScreenSource::rendered('Pages/Domains/Index.jsx')
        );
    }

    /**
     * The stored value is never shown. An administrator reads a sentence.
     *
     * Mutation: render domain.expectation instead of expectationLabel.
     */
    public function test_the_stored_expectation_value_is_never_rendered(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->domains->domain(
            $organisation,
            'Finance',
            'finance',
            expectation: AccessExpectation::Exceptional
        );

        $props = $this->actingAsUser($admin)->get('/console/domains')->viewData('page')['props'];

        $this->assertSame(
            'Access is expected to be tightly limited and reviewed',
            $props['domains']['data'][0]['expectationLabel']
        );

        // The list carries the SENTENCE and does not carry the raw value.
        $this->assertArrayNotHasKey('expectation', $props['domains']['data'][0]);
    }

    /**
     * N50. Empty-because-there-are-none and empty-because-filtered say
     * DIFFERENT things.
     *
     * P1-03 shipped a group screen that said "nobody has ever been in this
     * group" whenever a filter matched nothing, which for a group with history
     * was untrue. Two different facts, distinguished from the start here.
     *
     * Mutation: use one message for both; drop the anyDomains prop.
     */
    public function test_an_empty_list_distinguishes_no_domains_from_no_matches(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $none = $this->actingAsUser($admin)->get('/console/domains')->viewData('page')['props'];

        $this->assertFalse($none['anyDomains']);
        $this->assertSame([], $none['domains']['data']);

        $this->domains->domain($organisation, 'Finance', 'finance');

        $filtered = $this->actingAsUser($admin)
            ->get('/console/domains?search=nothing-matches-this')
            ->viewData('page')['props'];

        $this->assertTrue($filtered['anyDomains'], 'A filtered-empty list claims there are no domains at all.');
        $this->assertSame([], $filtered['domains']['data']);

        $rendered = ScreenSource::rendered('Pages/Domains/Index.jsx');

        $this->assertStringContainsString('No business domains yet.', $rendered);
        $this->assertStringContainsString('No domains match these filters.', $rendered);
    }

    /**
     * N49. Search, filter and pagination work against volume, and the filters
     * SURVIVE PAGING.
     *
     * P1-03 shipped "Page 1 of 3" with no way to reach page 2. The component
     * exists now and carries the query string, so page 2 of a filtered list is
     * still filtered.
     *
     * Mutation: remove withQueryString(); ignore a filter; drop the limit.
     */
    public function test_search_filter_and_pagination_work_against_volume(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        for ($i = 1; $i <= 60; $i++) {
            $this->domains->domain(
                $organisation,
                sprintf('Domain %02d', $i),
                sprintf('domain-%02d', $i),
                kind: $i % 2 === 0 ? DomainKind::Baseline : DomainKind::Custom,
            );
        }

        $first = $this->actingAsUser($admin)->get('/console/domains')->viewData('page')['props'];

        $this->assertCount(25, $first['domains']['data'], 'The page size is not being applied.');
        $this->assertSame(60, $first['domains']['total']);
        $this->assertSame(3, $first['domains']['lastPage']);

        // A filter narrows it, and page 2 of the filtered list is STILL filtered.
        $filtered = $this->actingAsUser($admin)
            ->get('/console/domains?kind=custom')
            ->viewData('page')['props'];

        $this->assertSame(30, $filtered['domains']['total']);

        $secondPage = $this->actingAsUser($admin)
            ->get('/console/domains?kind=custom&page=2')
            ->viewData('page')['props'];

        $this->assertSame(30, $secondPage['domains']['total'], 'The filter was lost on page 2.');
        $this->assertSame(2, $secondPage['domains']['currentPage']);
        $this->assertSame('custom', $secondPage['filters']['kind']);

        foreach ($secondPage['domains']['data'] as $row) {
            $this->assertSame('custom', $row['kind']);
        }

        // Search reaches name, code and description.
        $this->domains->domain($organisation, 'Findable', 'findable', description: 'A distinctive phrase');

        foreach (['Findable', 'findable', 'distinctive'] as $term) {
            $hits = $this->actingAsUser($admin)
                ->get('/console/domains?search='.$term)
                ->viewData('page')['props'];

            $this->assertSame(1, $hits['domains']['total'], "Search did not find [{$term}].");
        }
    }

    /** The owner filters answer from the open ownership row, not a stored flag. */
    public function test_the_owner_filters_are_computed_from_ownership(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $active = $this->make->user($organisation);
        $inactive = $this->make->user($organisation);

        $this->domains->domain($organisation, 'Unowned', 'unowned');
        $this->domains->enabledWithOwner($organisation, $active, 'Owned', 'owned');
        $this->domains->enabledWithOwner($organisation, $inactive, 'Drifted', 'drifted');

        $inactive->forceFill(['status' => 'inactive'])->save();

        foreach ([
            'unassigned' => 'Unowned',
            'assigned' => null,
            'attention' => 'Drifted',
        ] as $filter => $expected) {
            $props = $this->actingAsUser($admin)
                ->get('/console/domains?owner='.$filter)
                ->viewData('page')['props'];

            if ($expected !== null) {
                $this->assertSame(1, $props['domains']['total'], "The [{$filter}] filter matched the wrong number.");
                $this->assertSame($expected, $props['domains']['data'][0]['name']);
            } else {
                $this->assertSame(2, $props['domains']['total']);
            }
        }
    }

    /**
     * The record page shows the identity code, read-only, and says why it never
     * changes. An administrator comparing two deployments needs the value that
     * joins them.
     */
    public function test_the_identity_code_is_shown_and_explained(): void
    {
        $rendered = ScreenSource::rendered('Pages/Domains/Record.jsx');

        $this->assertStringContainsString('Identity code', $rendered);
        $this->assertStringContainsString('This never changes, even if the name does.', $rendered);
        $this->assertStringContainsString('readOnly', $rendered);
    }

    /**
     * No implementation term reaches a user-facing surface.
     *
     * The screens render enum LABELS, never stored values, and no table or
     * column name appears in copy.
     */
    public function test_no_implementation_wording_reaches_the_screens(): void
    {
        foreach (['Pages/Domains/Index.jsx', 'Pages/Domains/Record.jsx', 'Components/DomainsPage.jsx'] as $screen) {
            $rendered = ScreenSource::rendered($screen);

            foreach ([
                'business_domains', 'business_domain_owners', 'access_expectation>', 'organisation_id',
                'DomainStatus', 'DomainKind', 'ended_at', 'assigned_at', 'lockForUpdate',
            ] as $term) {
                $this->assertStringNotContainsString(
                    $term,
                    $rendered,
                    "[{$screen}] exposes the implementation term [{$term}]."
                );
            }
        }
    }

    /** The status pill reads Enabled or Disabled, never active or inactive. */
    public function test_a_domain_is_enabled_or_disabled_and_never_active(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $this->domains->domain($organisation);

        $props = $this->actingAsUser($admin)->get('/console/domains')->viewData('page')['props'];

        $this->assertSame('Disabled', $props['domains']['data'][0]['statusLabel']);

        // A USER is active or inactive; a DOMAIN is switched on or off by the
        // organisation. Sharing the vocabulary would invite the reader to think
        // they mean the same thing to the system, and they do not.
        $this->assertNotSame('Inactive', $props['domains']['data'][0]['statusLabel']);
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
