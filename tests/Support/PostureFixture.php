<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Security\Posture\ControlScope;
use App\Modules\Security\Posture\ExceptionKind;
use App\Modules\Security\Posture\MetricRow;
use App\Modules\Security\Posture\PostureReport;
use App\Modules\Security\Posture\PostureRow;
use App\Modules\Security\Posture\PostureState;

/**
 * Hand-built posture reports, for the cases production cannot safely produce.
 *
 * D-83 makes NotApplicable fixture-only in Release 1, and the aggregation
 * contract still has to be right before the first real instance arrives - so
 * N-SS3a and N-SS3c are exercised from here rather than from a screen. Several
 * other cases are fixture-only for a different reason: inducing a Critical
 * state in production means breaking a live control, and a second System
 * Administrator must not be manufactured.
 *
 * FIXTURES ARE BUILT TO DIFFER. Where a value matters, it is chosen to be
 * distinct - two domains with identical counts cannot detect contamination, and
 * two viewers whose payloads coincide cannot detect a leak.
 */
final class PostureFixture
{
    /** @var list<PostureRow> */
    private array $rows = [];

    /** @var list<MetricRow> */
    private array $metrics = [];

    public function platformRow(string $control, PostureState $state, string $finding = 'A platform finding.'): self
    {
        $this->rows[] = PostureRow::make(
            $control,
            "Platform control {$control}",
            ControlScope::Platform,
            ExceptionKind::Unresolved,
            $state,
            $finding,
            'identity.entra',
            'Identity & SSO',
        );

        return $this;
    }

    public function organisationRow(string $control, PostureState $state, string $finding = 'An organisation finding.'): self
    {
        $this->rows[] = PostureRow::make(
            $control,
            "Organisation control {$control}",
            ControlScope::Organisation,
            ExceptionKind::Unresolved,
            $state,
            $finding,
            'access.index',
            'Roles & Access',
        );

        return $this;
    }

    public function metric(string $control, int $count, ControlScope $scope = ControlScope::Organisation): self
    {
        $this->metrics[] = MetricRow::make(
            $control,
            "Metric {$control}",
            $scope,
            $count,
            'A count, not a finding.',
        );

        return $this;
    }

    public function report(): PostureReport
    {
        return new PostureReport($this->rows, $this->metrics);
    }
}
