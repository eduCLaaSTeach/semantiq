<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Modules\Security\Posture\ControlScope;
use App\Modules\Security\Posture\MetricRow;
use App\Modules\Security\Posture\PostureState;
use App\Modules\Security\Projection\PostureProjection;
use App\Modules\Security\Projection\Viewer;
use App\Modules\Security\Projection\ViewerMetric;
use App\Modules\Security\Projection\ViewerRow;
use App\Modules\Security\Projection\WithheldRow;
use Tests\Support\PostureFixture;
use Tests\TestCase;

/**
 * N-SS16, N-SS17, N-SS26, N-SS36 - THE PROJECTION, AND EVERY LEAK CHANNEL.
 *
 * The headline case is test_a_platform_value_is_not_inferable..., which asserts
 * BYTE-IDENTICAL payloads for two fixtures differing only in a hidden value. An
 * assertion that merely compared aggregates would pass while the ordering
 * leaked; comparing the serialised bytes closes every channel at once,
 * including ones nobody thought to enumerate.
 */
final class ViewerProjectionTest extends TestCase
{
    /**
     * N-SS26. THE WHOLE POINT OF D-76.
     *
     * Two deployments identical except that one has a CRITICAL platform row
     * where the other has a HEALTHY one. An Organisation Administrator's
     * payload must be the same bytes.
     *
     * Mutations this catches, all at once:
     *   - compute the aggregate over all rows rather than valued rows;
     *   - sort rows by state ("show the problems first");
     *   - count withheld rows in the exception total or the caption;
     *   - give a withheld row a state-derived class, icon or finding;
     *   - omit a withheld row when it is critical;
     *   - emit a different field set for a withheld row.
     */
    public function test_a_platform_value_is_not_inferable_from_anything_an_organisation_administrator_sees(): void
    {
        $healthy = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->platformRow('PLAT-1', PostureState::Healthy)
            ->organisationRow('ORG-2', PostureState::Healthy)
            ->platformRow('PLAT-2', PostureState::Healthy)
            ->report();

        $critical = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->platformRow('PLAT-1', PostureState::Critical)
            ->organisationRow('ORG-2', PostureState::Healthy)
            ->platformRow('PLAT-2', PostureState::Unverified)
            ->report();

        $viewer = Viewer::withPlatformValues(false);

        $a = PostureProjection::for($healthy, $viewer);
        $b = PostureProjection::for($critical, $viewer);

        $this->assertSame(
            json_encode($a->toArray()),
            json_encode($b->toArray()),
            'An Organisation Administrator can tell a critical platform condition from a healthy '
            .'one. The hidden value is leaking through the payload.',
        );

        // And, so the test cannot pass by both payloads being empty or broken:
        // the rows ARE named, and a System Administrator CAN tell them apart.
        $this->assertCount(4, $a->rows);
        $this->assertCount(2, $a->withheld());

        $full = Viewer::withPlatformValues(true);

        $this->assertNotSame(
            json_encode(PostureProjection::for($healthy, $full)->toArray()),
            json_encode(PostureProjection::for($critical, $full)->toArray()),
            'A System Administrator must be able to see the difference, or the fixtures do not '
            .'differ and the assertion above proves nothing.',
        );
    }

    /**
     * A withheld row is NAMED. Its existence, position and label are the same
     * for every viewer; only the value is absent.
     */
    public function test_a_withheld_row_is_named_but_carries_no_value(): void
    {
        $report = (new PostureFixture)
            ->platformRow('PLAT-1', PostureState::Critical, 'The last administrator has gone.')
            ->report();

        $rows = PostureProjection::for($report, Viewer::withPlatformValues(false))->rows;

        $this->assertInstanceOf(WithheldRow::class, $rows[0]);
        $this->assertSame('Platform control PLAT-1', $rows[0]->label);

        $rendered = $rows[0]->toArray();

        $this->assertNull($rendered['state']);
        $this->assertNull($rendered['stateLabel']);
        $this->assertNull($rendered['ownerHref']);
        $this->assertTrue($rendered['withheld']);
        $this->assertSame(WithheldRow::SENTENCE, $rendered['finding']);

        $this->assertStringNotContainsString(
            'administrator has gone',
            json_encode($rendered) ?: '',
            'The real finding reached the payload of a row whose value is withheld.',
        );

        // THE STRUCTURAL GUARANTEE: there is no state field to leak through.
        $this->assertFalse(
            property_exists(WithheldRow::class, 'state'),
            'WithheldRow has a state property. A nullable state is a FILTER, and filters are what '
            .'later edits remove - the whole design is that there is nothing to strip.',
        );
    }

    /** The payload SHAPE is invariant: both row types emit the same keys. */
    public function test_a_withheld_row_and_a_valued_row_emit_the_same_field_set(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->platformRow('PLAT-1', PostureState::Critical)
            ->report();

        $rows = PostureProjection::for($report, Viewer::withPlatformValues(false))->rows;

        $this->assertSame(
            array_keys($rows[0]->toArray()),
            array_keys($rows[1]->toArray()),
            'A response whose field set changes with the viewer\'s authority is itself the '
            .'disclosure.',
        );
    }

    /**
     * ORDERING IS CATALOGUE ORDER. Never sorted by state, for any viewer.
     *
     * Mutation: sort rows worst-first. Sorting turns a withheld row's POSITION
     * into its value, and "show the problems first" is the well-meaning
     * improvement most likely to introduce it.
     */
    public function test_rows_are_never_reordered_by_state(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-HEALTHY', PostureState::Healthy)
            ->organisationRow('ORG-CRITICAL', PostureState::Critical)
            ->organisationRow('ORG-ATTENTION', PostureState::Attention)
            ->report();

        $rows = PostureProjection::for($report, Viewer::withPlatformValues(true))->rows;

        $this->assertSame(
            ['ORG-HEALTHY', 'ORG-CRITICAL', 'ORG-ATTENTION'],
            array_map(static fn ($row): string => $row->control, $rows),
        );
    }

    /**
     * N-SS16. A count can never enter aggregation.
     *
     * Adding ANY number of metrics, in any state of the world, must not change
     * the aggregate - because MetricRow has no state to contribute.
     */
    public function test_an_informational_metric_can_never_change_the_aggregate(): void
    {
        $bare = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->report();

        $loaded = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->metric('PR-2', 7)
            ->metric('PR-4', 99)
            ->metric('PR-5', 3)
            ->metric('PR-8', 42)
            ->report();

        $viewer = Viewer::withPlatformValues(true);

        $this->assertSame(
            PostureState::Healthy,
            PostureProjection::for($bare, $viewer)->aggregate(),
        );

        $this->assertSame(
            PostureState::Healthy,
            PostureProjection::for($loaded, $viewer)->aggregate(),
            'An informational metric changed the aggregate. A count is not evidence of anything.',
        );

        $this->assertCount(4, PostureProjection::for($loaded, $viewer)->metrics);
    }

    /**
     * N-SS17. A count is never converted into Healthy, and never appears among
     * the exceptions - there is no state on it to convert or to test.
     */
    public function test_a_metric_carries_no_state_and_is_never_an_exception(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->metric('PR-4', 5)
            ->report();

        $projected = PostureProjection::for($report, Viewer::withPlatformValues(true));

        $this->assertSame([], $projected->exceptions());

        $this->assertFalse(
            property_exists(ViewerMetric::class, 'state'),
            'ViewerMetric has a state property, so a count can be given one.',
        );

        $this->assertFalse(
            property_exists(MetricRow::class, 'state'),
            'MetricRow has a state property. That is the F-5 mutation: a legitimate Restricted '
            .'grant could then turn posture amber.',
        );
    }

    /**
     * N-SS36. A not_applicable row is excluded from the unresolved total, and
     * a withheld row cannot pad it either.
     */
    public function test_not_applicable_rows_and_withheld_rows_are_excluded_from_the_exception_count(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->organisationRow('ORG-NA', PostureState::NotApplicable)
            ->platformRow('PLAT-CRIT', PostureState::Critical)
            ->report();

        $orgAdmin = PostureProjection::for($report, Viewer::withPlatformValues(false));

        $this->assertSame(
            [],
            $orgAdmin->exceptions(),
            'A not_applicable row or a withheld critical is padding a list that can then never '
            .'reach zero.',
        );

        $this->assertSame(PostureState::Healthy, $orgAdmin->aggregate());
        $this->assertCount(1, $orgAdmin->notApplicable());
    }

    /**
     * A viewer with withheld rows NEVER sees an unqualified aggregate.
     *
     * "Healthy" on its own is a claim about the whole deployment, which this
     * viewer has not been shown. Mutation: return the bare label.
     */
    public function test_a_viewer_with_withheld_rows_never_sees_an_unqualified_aggregate(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->platformRow('PLAT-1', PostureState::Healthy)
            ->report();

        $this->assertSame(
            'Healthy — organisation controls',
            PostureProjection::for($report, Viewer::withPlatformValues(false))->aggregateLabel(),
        );

        $this->assertSame(
            'Healthy',
            PostureProjection::for($report, Viewer::withPlatformValues(true))->aggregateLabel(),
            'A System Administrator sees the whole deployment, so their aggregate is unqualified.',
        );
    }

    /** A platform METRIC is a value too, and is not published to a viewer who may not see it. */
    public function test_a_platform_metric_is_not_published_to_an_organisation_administrator(): void
    {
        $report = (new PostureFixture)
            ->metric('ORG-METRIC', 3)
            ->metric('PLAT-METRIC', 11, ControlScope::Platform)
            ->report();

        $orgAdmin = PostureProjection::for($report, Viewer::withPlatformValues(false));

        $this->assertCount(1, $orgAdmin->metrics);
        $this->assertSame('ORG-METRIC', $orgAdmin->metrics[0]->control);

        $this->assertStringNotContainsString(
            '11',
            json_encode(array_map(static fn ($m) => $m->toArray(), $orgAdmin->metrics)) ?: '',
        );

        $this->assertCount(2, PostureProjection::for($report, Viewer::withPlatformValues(true))->metrics);
    }

    /** The caption counts VALUED rows, and names withheld ones separately. */
    public function test_the_caption_counts_valued_rows_and_names_withheld_ones_separately(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->organisationRow('ORG-2', PostureState::Unverified)
            ->platformRow('PLAT-1', PostureState::Critical)
            ->report();

        $caption = PostureProjection::for($report, Viewer::withPlatformValues(false))->caption();

        $this->assertStringContainsString('2 applicable controls reported', $caption);
        $this->assertStringContainsString('1 not verified', $caption);
        $this->assertStringContainsString('1 platform control is managed by the platform administrator', $caption);

        // The withheld critical must not have been folded into "not verified".
        $this->assertStringNotContainsString('2 not verified', $caption);
    }

    /** The exception list and the count are ONE derivation. */
    public function test_the_exception_count_and_the_exception_list_cannot_disagree(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Attention)
            ->organisationRow('ORG-2', PostureState::Unverified)
            ->organisationRow('ORG-3', PostureState::Healthy)
            ->report();

        $projected = PostureProjection::for($report, Viewer::withPlatformValues(true));

        $this->assertSame(
            count($projected->exceptions()),
            $projected->toArray()['exceptionCount'],
        );

        $this->assertCount(2, $projected->exceptions());
    }

    /** A valued row is a ViewerRow; a withheld one is a different type entirely. */
    public function test_the_two_row_kinds_are_different_types(): void
    {
        $report = (new PostureFixture)
            ->organisationRow('ORG-1', PostureState::Healthy)
            ->platformRow('PLAT-1', PostureState::Healthy)
            ->report();

        $rows = PostureProjection::for($report, Viewer::withPlatformValues(false))->rows;

        $this->assertInstanceOf(ViewerRow::class, $rows[0]);
        $this->assertInstanceOf(WithheldRow::class, $rows[1]);
    }
}
