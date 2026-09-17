<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

use App\Modules\Security\Posture\ControlScope;
use App\Modules\Security\Posture\DomainPosture;
use App\Modules\Security\Posture\MetricRow;
use App\Modules\Security\Posture\PostureReport;
use App\Modules\Security\Posture\PostureRow;
use Illuminate\Support\Facades\Route;

/**
 * THE BOUNDARY. PostureReport + Viewer → ViewerReport, and nothing renders
 * except what comes out of here.
 *
 * WHAT IS WITHHELD IS DECIDED BY SCOPE, NEVER BY VALUE. That is the property
 * that closes the whole family of leaks at once: because the decision is read
 * from the catalogue rather than from the answer, an Organisation
 * Administrator's payload is IDENTICAL whatever the platform rows actually say.
 * Row count, row order, field set, wording, classes and counts are all fixed by
 * the catalogue; only the values differ, and those are the part they do not
 * receive.
 *
 * EVERY CHANNEL, NAMED:
 *
 *   aggregate        computed over ViewerRows only - a WithheldRow has no state
 *                    to contribute (ViewerReport::aggregate()).
 *   wording          a viewer with withheld rows never sees an unqualified
 *                    aggregate (ViewerReport::aggregateLabel()).
 *   exception count  exceptions() filters ViewerRows only; a withheld row is
 *                    not one, so it cannot appear or be counted.
 *   tab count        the same method. One derivation, one number.
 *   caption totals   valued rows are counted; withheld rows are counted
 *                    SEPARATELY AND BY NAME, so a withheld critical cannot pad
 *                    "not verified".
 *   ordering         CATALOGUE ORDER ONLY. Never sorted by state, for anybody.
 *                    "Show the problems first" is the well-meaning improvement
 *                    that turns a withheld row's POSITION into its value.
 *   colour and icon  a withheld row has no state, so no state-derived class can
 *                    be computed for it; the screen gives it one neutral
 *                    treatment.
 *   row count        the same rows are NAMED for every viewer, always. A row
 *                    omitted when critical and present when healthy would be a
 *                    disclosure by absence.
 *   remediation link WithheldRow has no ownerHref field.
 *   payload shape    toArray() emits the SAME KEYS for both row types, with
 *                    constants in the withheld ones.
 *
 * N-SS26 asserts all of it at once, by rendering two fixtures that differ ONLY
 * in a platform row's underlying value and requiring BYTE-IDENTICAL payloads.
 * An assertion that merely checked the aggregate would pass while the ordering
 * leaked.
 */
final class PostureProjection
{
    public static function for(PostureReport $report, Viewer $viewer): ViewerReport
    {
        return new ViewerReport(
            rows: array_map(
                static fn (PostureRow $row): ViewerRow|WithheldRow => self::row($row, $viewer),
                $report->rows,
            ),
            metrics: self::metrics($report->metrics, $viewer),
            domains: array_map(
                static fn (DomainPosture $domain): ViewerDomain => self::domain($domain),
                $report->domains,
            ),
            seesPlatformValues: $viewer->seesPlatformValues,
        );
    }

    /** A projection of an arbitrary row set, for one screen's section. */
    public static function rows(array $rows, Viewer $viewer): array
    {
        return array_map(
            static fn (PostureRow $row): ViewerRow|WithheldRow => self::row($row, $viewer),
            $rows,
        );
    }

    private static function row(PostureRow $row, Viewer $viewer): ViewerRow|WithheldRow
    {
        if ($row->scope === ControlScope::Platform && ! $viewer->seesPlatformValues) {
            // NAMED, NOT VALUED. The returned type has no state field at all.
            return new WithheldRow($row->control, $row->label);
        }

        return new ViewerRow(
            $row->control,
            $row->label,
            $row->state,
            $row->kind,
            $row->finding,
            self::href($row->ownerRoute),
            $row->ownerLabel,
            $row->qualifier,
        );
    }

    /**
     * A platform-scoped METRIC is omitted for a viewer who may not value it -
     * a count is a value.
     *
     * Omission here is safe precisely because it is decided by SCOPE: the same
     * metrics are omitted for that viewer on every deployment and in every
     * state, so their presence or absence says nothing about the answer. Every
     * Release-1 metric is organisation-scoped, so in practice no metric is
     * dropped; the branch exists so that adding a platform metric later cannot
     * silently publish a number.
     *
     * @param  list<MetricRow>  $metrics
     * @return list<ViewerMetric>
     */
    private static function metrics(array $metrics, Viewer $viewer): array
    {
        $projected = [];

        foreach ($metrics as $metric) {
            if ($metric->scope === ControlScope::Platform && ! $viewer->seesPlatformValues) {
                continue;
            }

            $projected[] = new ViewerMetric(
                $metric->control,
                $metric->label,
                $metric->count,
                $metric->context,
            );
        }

        return $projected;
    }

    private static function domain(DomainPosture $domain): ViewerDomain
    {
        return new ViewerDomain(
            $domain->id,
            $domain->name,
            $domain->enabled,
            array_map(
                static fn (PostureRow $row): ViewerRow => new ViewerRow(
                    $row->control,
                    $row->label,
                    $row->state,
                    $row->kind,
                    $row->finding,
                    self::href($row->ownerRoute),
                    $row->ownerLabel,
                    $row->qualifier,
                ),
                $domain->rows,
            ),
            array_map(
                static fn (MetricRow $metric): ViewerMetric => new ViewerMetric(
                    $metric->control,
                    $metric->label,
                    $metric->count,
                    $metric->context,
                ),
                $domain->metrics,
            ),
        );
    }

    /**
     * A ROUTE NAME becomes a URL here and nowhere else.
     *
     * Never a hand-typed path: a typo would be a dead link on a screen whose
     * whole value is telling somebody where to go. A name that does not resolve
     * yields null rather than throwing, so a link cannot take the page down.
     */
    private static function href(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }

        if (Route::has($routeName)) {
            return route($routeName, absolute: false);
        }

        return null;
    }
}
