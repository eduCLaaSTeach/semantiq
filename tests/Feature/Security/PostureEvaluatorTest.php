<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Adapters\AdministratorAdapter;
use App\Modules\Security\Posture\Adapters\CarriedGateAdapter;
use App\Modules\Security\Posture\Adapters\DomainAdapter;
use App\Modules\Security\Posture\Adapters\EngineGateAdapter;
use App\Modules\Security\Posture\Adapters\GrantPathAdapter;
use App\Modules\Security\Posture\Adapters\HostingAdapter;
use App\Modules\Security\Posture\Adapters\IdentityAdapter;
use App\Modules\Security\Posture\Adapters\RouteCoverageAdapter;
use App\Modules\Security\Posture\Adapters\SessionAdapter;
use App\Modules\Security\Posture\Adapters\StepUpAdapter;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureEvaluator;
use App\Modules\Security\Posture\PostureRow;
use App\Modules\Security\Posture\PostureState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeIdentityAdapter;
use Tests\Support\StubAdapter;
use Tests\TestCase;

/**
 * N-SS5, N-SS6, N-SS7, N-SS8 - FAIL CLOSED, WITH THE ROW STILL PRESENT.
 *
 * The tempting wrong implementation is catch-and-continue. It looks tidy, it
 * makes the screen render, and it removes the row - so the reader counts what
 * they see and reaches a conclusion the deployment does not support. Omission
 * is the failure mode that makes a posture screen lie.
 */
final class PostureEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    private function evaluator(StubAdapter ...$adapters): PostureEvaluator
    {
        return new PostureEvaluator(app(DomainAdapter::class), ...$adapters);
    }

    /** @return array<string, PostureRow> */
    private function rowsByControl(PostureEvaluator $evaluator): array
    {
        $rows = [];

        foreach ($evaluator->evaluate()->rows as $row) {
            $rows[$row->control] = $row;
        }

        return $rows;
    }

    /**
     * N-SS5. A THROWING SOURCE YIELDS UNVERIFIED AND THE ROW STILL RENDERS.
     *
     * Mutation: catch and `continue`, dropping the row.
     */
    public function test_a_throwing_source_yields_unverified_and_the_row_still_renders(): void
    {
        $rows = $this->rowsByControl($this->evaluator(
            new StubAdapter([ControlCatalogue::IDENTITY_TRUST], throws: true),
        ));

        $this->assertArrayHasKey(
            ControlCatalogue::IDENTITY_TRUST,
            $rows,
            'The row vanished. A posture screen that drops a row it could not evaluate lies by '
            .'omission, because the reader counts what they see.',
        );

        $this->assertSame(PostureState::Unverified, $rows[ControlCatalogue::IDENTITY_TRUST]->state);
        $this->assertNotSame(PostureState::Healthy, $rows[ControlCatalogue::IDENTITY_TRUST]->state);
    }

    /**
     * THE ERROR BOUNDARY. A thrown message never reaches a rendered finding.
     *
     * An exception message is where a connection string, a table name, a
     * filesystem path or a provider payload would cross onto a screen. The
     * evaluator discards the throwable rather than formatting it.
     */
    public function test_a_thrown_message_never_reaches_a_rendered_finding(): void
    {
        $report = $this->evaluator(
            new StubAdapter([ControlCatalogue::IDENTITY_TRUST], throws: true),
        )->evaluate();

        $serialised = json_encode(array_map(
            static fn (PostureRow $row): array => [$row->control, $row->finding],
            $report->rows,
        )) ?: '';

        $this->assertStringNotContainsString(StubAdapter::SECRET_IN_THE_EXCEPTION, $serialised);
        $this->assertStringNotContainsString('SQLSTATE', $serialised);
        $this->assertStringNotContainsString('semantiq_prod', $serialised);
    }

    /**
     * N-SS5, second half. A source that returns NOTHING where rows were
     * expected is Unverified, not Healthy.
     */
    public function test_a_source_that_returns_nothing_is_unverified_rather_than_healthy(): void
    {
        $rows = $this->rowsByControl($this->evaluator(
            new StubAdapter([ControlCatalogue::IDENTITY_TRUST], evidence: []),
        ));

        $this->assertSame(PostureState::Unverified, $rows[ControlCatalogue::IDENTITY_TRUST]->state);
    }

    /**
     * ONE ADAPTER FAILING MUST NOT TAKE THE OTHERS WITH IT.
     *
     * Mutation: wrap the whole gather loop in one try instead of catching per
     * adapter. Every row would then go unverified because one source was down,
     * which is its own kind of dishonesty.
     */
    public function test_one_failing_adapter_does_not_take_the_others_with_it(): void
    {
        $rows = $this->rowsByControl($this->evaluator(
            new StubAdapter([ControlCatalogue::IDENTITY_TRUST], throws: true),
            new StubAdapter([ControlCatalogue::APPROVED_PROVIDERS], [
                Evidence::state(ControlCatalogue::APPROVED_PROVIDERS, PostureState::Healthy, 'Fine.'),
            ]),
        ));

        $this->assertSame(PostureState::Unverified, $rows[ControlCatalogue::IDENTITY_TRUST]->state);
        $this->assertSame(PostureState::Healthy, $rows[ControlCatalogue::APPROVED_PROVIDERS]->state);
    }

    /**
     * N-SS7. EVERY CATALOGUED CONTROL HAS AN EVALUATOR.
     *
     * An architecture guard on the real wiring. Mutation: add a control to
     * ControlCatalogue without an adapter that answers for it - it would
     * otherwise render permanently unverified and nobody would notice, because
     * "unverified" is a legitimate answer.
     */
    public function test_every_catalogued_control_has_an_adapter_that_answers_for_it(): void
    {
        $answered = app(PostureEvaluator::class)->answered();

        $missing = array_values(array_diff(ControlCatalogue::ids(), $answered));

        $this->assertSame(
            [],
            $missing,
            'These controls are in the catalogue with nothing to evaluate them: '
            .implode(', ', $missing).'. They would render as permanently unverified, which is '
            .'indistinguishable from an honest answer.',
        );
    }

    /** And nothing answers for a control that is not catalogued. */
    public function test_no_adapter_answers_for_a_control_that_is_not_catalogued(): void
    {
        $orphans = array_values(array_diff(
            app(PostureEvaluator::class)->answered(),
            ControlCatalogue::ids(),
        ));

        $this->assertSame([], $orphans, 'Evidence is produced for controls nobody asked about: '.implode(', ', $orphans));
    }

    /**
     * N-SS8. AN UNRECOGNISED STORED VALUE YIELDS UNVERIFIED - and nothing else.
     *
     * Not a crash, not a guess, and NO SECURITY EVENT. P1-06 records nothing on
     * any path, including this one; access.state.unrecognised belongs to the
     * engine that makes access decisions, not to a reporting screen.
     */
    public function test_an_unrecognised_identity_row_state_becomes_unverified(): void
    {
        $adapter = new FakeIdentityAdapter('a_state_nobody_declared');

        $mapped = $adapter->mapForTest();

        $this->assertSame(PostureState::Unverified, $mapped);
    }

    /**
     * N-SS6. TWO SOURCES THAT DISAGREE yield Attention, naming both - never
     * silently preferring one.
     *
     * The disagreement is itself the finding: two parts of the product that
     * cannot agree about the same fact is a condition worth an administrator's
     * attention, whichever of them is right.
     */
    public function test_two_sources_that_disagree_produce_attention_naming_both(): void
    {
        $rows = $this->rowsByControl($this->evaluator(
            new StubAdapter([ControlCatalogue::STEP_UP_LOCAL], [
                Evidence::state(ControlCatalogue::STEP_UP_LOCAL, PostureState::Healthy, 'Sign-in confirmation is configured.'),
            ]),
            new StubAdapter([ControlCatalogue::STEP_UP_LOCAL], [
                Evidence::state(ControlCatalogue::STEP_UP_LOCAL, PostureState::Critical, 'Sign-in confirmation is missing.'),
            ]),
        ));

        $row = $rows[ControlCatalogue::STEP_UP_LOCAL];

        $this->assertSame(
            PostureState::Attention,
            $row->state,
            'One source silently overwrote the other. Picking a winner quietly is how a screen '
            .'reports a fact nobody verified.',
        );

        // BOTH are named, so an administrator can see what disagrees.
        $this->assertStringContainsString('Sign-in confirmation is configured', $row->finding);
        $this->assertStringContainsString('Sign-in confirmation is missing', $row->finding);

        // And it is not merely the worse of the two passed through.
        $this->assertNotSame(PostureState::Critical, $row->state);
        $this->assertNotSame(PostureState::Healthy, $row->state);
    }

    /** Two sources that AGREE are not a disagreement. */
    public function test_two_sources_that_agree_are_not_reported_as_a_conflict(): void
    {
        $rows = $this->rowsByControl($this->evaluator(
            new StubAdapter([ControlCatalogue::STEP_UP_LOCAL], [
                Evidence::state(ControlCatalogue::STEP_UP_LOCAL, PostureState::Healthy, 'Configured.'),
            ]),
            new StubAdapter([ControlCatalogue::STEP_UP_LOCAL], [
                Evidence::state(ControlCatalogue::STEP_UP_LOCAL, PostureState::Healthy, 'Configured.'),
            ]),
        ));

        $this->assertSame(PostureState::Healthy, $rows[ControlCatalogue::STEP_UP_LOCAL]->state);
        $this->assertStringNotContainsString('disagree', $rows[ControlCatalogue::STEP_UP_LOCAL]->finding);
    }

    /**
     * THE GUARD THAT KEEPS THE CONFLICT PATH UNREACHABLE IN RELEASE 1.
     *
     * No two adapters may answer for the same control. While that holds, a
     * conflict cannot arise; if somebody adds an overlapping adapter, this
     * fails rather than letting one source silently overwrite another.
     */
    public function test_no_two_adapters_answer_for_the_same_control(): void
    {
        $seen = [];
        $duplicated = [];

        foreach (app(PostureEvaluator::class)->answered() as $control) {
            // answered() de-duplicates, so the real check is against the
            // adapters themselves.
            $seen[] = $control;
        }

        $counts = [];

        foreach ($this->adapterAnswers() as $control) {
            $counts[$control] = ($counts[$control] ?? 0) + 1;

            if ($counts[$control] === 2) {
                $duplicated[] = $control;
            }
        }

        $this->assertSame(
            [],
            $duplicated,
            'Two adapters answer for the same control: '.implode(', ', $duplicated).'. One would '
            .'silently overwrite the other, which is the conflict N-SS6 forbids being resolved '
            .'silently.',
        );
    }

    /** @return list<string> */
    private function adapterAnswers(): array
    {
        $adapters = [
            IdentityAdapter::class,
            SessionAdapter::class,
            RouteCoverageAdapter::class,
            EngineGateAdapter::class,
            AdministratorAdapter::class,
            StepUpAdapter::class,
            GrantPathAdapter::class,
            HostingAdapter::class,
            DomainAdapter::class,
            CarriedGateAdapter::class,
        ];

        $all = [];

        foreach ($adapters as $class) {
            foreach (app($class)->answers() as $control) {
                $all[] = $control;
            }
        }

        return $all;
    }
}
