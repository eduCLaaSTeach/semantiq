<?php

declare(strict_types=1);

namespace App\Modules\Security\Projection;

use App\Modules\Security\Posture\Aggregation;
use App\Modules\Security\Posture\PostureState;

/**
 * What one viewer sees. The ONLY thing a controller renders.
 *
 * THE AGGREGATE IS COMPUTED OVER THIS VIEWER'S VALUED ROWS ONLY. A WithheldRow
 * has no state to contribute, so it contributes nothing - not because a rule
 * excludes it, but because there is nothing to pass to Aggregation::of().
 *
 * ONE DERIVATION, ONE NUMBER. exceptions() is the single source of the list,
 * the tab count and the heading count. An "Exceptions (3)" badge that counted
 * separately is exactly how a count and a list come to disagree.
 */
final class ViewerReport
{
    /**
     * @param  list<ViewerRow|WithheldRow>  $rows
     * @param  list<ViewerMetric>  $metrics
     * @param  list<ViewerDomain>  $domains
     */
    public function __construct(
        public readonly array $rows,
        public readonly array $metrics,
        public readonly array $domains = [],
        public readonly bool $seesPlatformValues = true,
    ) {}

    /** @return list<ViewerRow> */
    public function valued(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ViewerRow|WithheldRow $row): bool => $row instanceof ViewerRow,
        ));
    }

    /**
     * The rows belonging to one screen, IN CATALOGUE ORDER.
     *
     * A filter over the already-projected set, never a second query and never a
     * re-projection: every screen shows the same rows the aggregate was
     * computed from, so a tab cannot disagree with the badge above it.
     *
     * @return list<ViewerRow|WithheldRow>
     */
    public function only(string ...$controls): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ViewerRow|WithheldRow $row): bool => in_array($row->control, $controls, true),
        ));
    }

    /** @return list<ViewerMetric> */
    public function metricsOnly(string ...$controls): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (ViewerMetric $metric): bool => in_array($metric->control, $controls, true),
        ));
    }

    /** @return list<WithheldRow> */
    public function withheld(): array
    {
        return array_values(array_filter(
            $this->rows,
            static fn (ViewerRow|WithheldRow $row): bool => $row instanceof WithheldRow,
        ));
    }

    /**
     * EVERY row this viewer can value, INCLUDING per-domain rows.
     *
     * Domain posture is posture. Leaving it out of the aggregate would let the
     * badge read Healthy while the section below it showed a domain needing
     * attention - an inconsistency a reader would notice immediately, and would
     * be right to distrust. It would also keep "enabled with nobody accountable
     * for it" out of Exceptions, which is exactly the kind of stored state that
     * escaped a UI refusal and is worth surfacing.
     *
     * @return list<ViewerRow>
     */
    public function contributing(): array
    {
        $rows = $this->valued();

        foreach ($this->domains as $domain) {
            foreach ($domain->rows as $row) {
                $rows[] = $row;
            }
        }

        return $rows;
    }

    public function aggregate(): PostureState
    {
        return Aggregation::of(array_map(
            static fn (ViewerRow $row): PostureState => $row->state,
            $this->contributing(),
        ));
    }

    /**
     * THE EXCEPTIONS. A filter over rows this viewer can VALUE, and nothing else.
     *
     * A withheld row cannot appear, because it is not a ViewerRow. A metric
     * cannot appear, because it is not in $rows at all and has no state to
     * test. A not_applicable row cannot appear, because isException() excludes
     * it BY NAME - it belongs in the "Not part of Release 1" area, and listing
     * it among unresolved conditions would produce a list that can never reach
     * zero, which is how an exceptions screen becomes wallpaper.
     *
     * @return list<ViewerRow>
     */
    public function exceptions(): array
    {
        return array_values(array_filter(
            $this->contributing(),
            static fn (ViewerRow $row): bool => $row->state->isException(),
        ));
    }

    /** @return list<ViewerRow> */
    public function notApplicable(): array
    {
        return array_values(array_filter(
            $this->contributing(),
            static fn (ViewerRow $row): bool => $row->state === PostureState::NotApplicable,
        ));
    }

    public function unverifiedCount(): int
    {
        return count(array_filter(
            $this->contributing(),
            static fn (ViewerRow $row): bool => $row->state === PostureState::Unverified,
        ));
    }

    /**
     * The aggregate as a person reads it.
     *
     * A VIEWER WITH WITHHELD ROWS NEVER SEES AN UNQUALIFIED AGGREGATE. "Healthy"
     * on its own would be a claim about the whole deployment, which this viewer
     * has not been shown. "Healthy — organisation controls" is a claim about
     * what they were shown, which is true.
     */
    public function aggregateLabel(): string
    {
        $label = $this->aggregate()->label();

        return $this->withheld() === [] ? $label : $label.' — organisation controls';
    }

    /**
     * The caption. Counts VALUED rows only; withheld rows are counted
     * SEPARATELY AND BY NAME, so a withheld critical cannot pad "not verified".
     */
    public function caption(): string
    {
        $reported = count($this->contributing());
        $unverified = $this->unverifiedCount();

        $sentence = $this->exceptions() === []
            ? 'No unresolved conditions. '
            : '';

        $sentence .= $reported === 1
            ? '1 applicable control reported, '
            : "{$reported} applicable controls reported, ";

        $sentence .= "{$unverified} not verified.";

        $withheld = count($this->withheld());

        if ($withheld > 0) {
            $sentence .= $withheld === 1
                ? ' 1 platform control is managed by the platform administrator.'
                : " {$withheld} platform controls are managed by the platform administrator.";
        }

        return $sentence;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'aggregate' => $this->aggregate()->value,
            'aggregateLabel' => $this->aggregateLabel(),
            'caption' => $this->caption(),
            'exceptionCount' => count($this->exceptions()),
            'rows' => array_map(
                static fn (ViewerRow|WithheldRow $row): array => $row->toArray(),
                $this->rows,
            ),
            'metrics' => array_map(
                static fn (ViewerMetric $metric): array => $metric->toArray(),
                $this->metrics,
            ),
        ];
    }
}
