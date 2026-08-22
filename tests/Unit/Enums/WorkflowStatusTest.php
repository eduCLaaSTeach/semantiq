<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\BadgeRole;
use App\Enums\WorkflowStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The ten-state status model, checked against SRS section 18.1 rather than
 * against itself.
 *
 * The mapping from ten states onto six badge roles is the part worth testing.
 * It is lossy, the losses were chosen in doc/execution/PHASE-00-PLAN.md, and a
 * later change that quietly re-colours a state would break a shared vocabulary
 * without breaking anything a compiler notices.
 */
class WorkflowStatusTest extends TestCase
{
    #[Test]
    public function the_srs_defines_exactly_ten_states(): void
    {
        $this->assertCount(10, WorkflowStatus::cases());
    }

    #[Test]
    public function the_persisted_codes_are_stable(): void
    {
        // These values are in the database. Changing one is a data migration,
        // not a rename, and this test is where that gets noticed.
        $this->assertSame([
            'not_started',
            'in_progress',
            'action_required',
            'approval_required',
            'ready',
            'succeeded',
            'warning',
            'failed',
            'drift_detected',
            'revalidation_required',
        ], array_map(fn (WorkflowStatus $s): string => $s->value, WorkflowStatus::cases()));
    }

    #[Test]
    public function every_state_carries_a_label_a_meaning_and_an_action(): void
    {
        foreach (WorkflowStatus::cases() as $status) {
            $this->assertNotSame('', $status->label(), $status->value.' has no label');
            $this->assertNotSame('', $status->meaning(), $status->value.' has no meaning');
            $this->assertNotSame('', $status->uiAction(), $status->value.' has no UI action');
        }
    }

    #[Test]
    public function the_labels_are_worded_as_the_srs_words_them(): void
    {
        $this->assertSame('Not Started', WorkflowStatus::NotStarted->label());
        $this->assertSame('Action Required', WorkflowStatus::ActionRequired->label());
        $this->assertSame('Approval Required', WorkflowStatus::ApprovalRequired->label());
        $this->assertSame('Drift Detected', WorkflowStatus::DriftDetected->label());
        $this->assertSame('Revalidation Required', WorkflowStatus::RevalidationRequired->label());
    }

    #[Test]
    public function every_state_resolves_to_a_badge_role_the_stylesheet_defines(): void
    {
        foreach (WorkflowStatus::cases() as $status) {
            $this->assertInstanceOf(BadgeRole::class, $status->badge());
        }
    }

    #[Test]
    public function the_badge_mapping_matches_the_approved_plan(): void
    {
        $expected = [
            'not_started' => BadgeRole::Neutral,
            'in_progress' => BadgeRole::Info,
            'action_required' => BadgeRole::Warning,
            'approval_required' => BadgeRole::Violet,
            'ready' => BadgeRole::Info,
            'succeeded' => BadgeRole::Success,
            'warning' => BadgeRole::Warning,
            'failed' => BadgeRole::Danger,
            'drift_detected' => BadgeRole::Warning,
            'revalidation_required' => BadgeRole::Warning,
        ];

        foreach (WorkflowStatus::cases() as $status) {
            $this->assertSame(
                $expected[$status->value],
                $status->badge(),
                $status->label().' is not rendering in its approved badge role'
            );
        }
    }

    #[Test]
    public function waiting_on_authority_looks_different_from_waiting_on_work(): void
    {
        // Two queues, two different people. If these ever share a colour the
        // distinction the SRS draws stops being visible on a screen.
        $this->assertNotSame(
            WorkflowStatus::ApprovalRequired->badge(),
            WorkflowStatus::ActionRequired->badge()
        );
    }

    #[Test]
    public function stale_evidence_is_not_presented_as_a_failure(): void
    {
        foreach ([WorkflowStatus::DriftDetected, WorkflowStatus::RevalidationRequired, WorkflowStatus::Warning] as $status) {
            $this->assertNotSame(BadgeRole::Danger, $status->badge());
        }
    }

    #[Test]
    public function only_in_progress_is_still_moving_by_itself(): void
    {
        $running = array_filter(WorkflowStatus::cases(), fn (WorkflowStatus $s): bool => $s->isRunning());

        $this->assertSame([WorkflowStatus::InProgress], array_values($running));
    }

    #[Test]
    public function the_states_that_wait_on_a_person_are_the_four_named_ones(): void
    {
        $waiting = array_values(array_filter(
            WorkflowStatus::cases(),
            fn (WorkflowStatus $s): bool => $s->awaitsPerson()
        ));

        $this->assertSame([
            WorkflowStatus::ActionRequired,
            WorkflowStatus::ApprovalRequired,
            WorkflowStatus::DriftDetected,
            WorkflowStatus::RevalidationRequired,
        ], $waiting);

        // A failure needs triage, which is not the same as a queue item.
        $this->assertFalse(WorkflowStatus::Failed->awaitsPerson());
    }

    #[Test]
    public function a_run_ends_on_succeeded_warning_or_failed(): void
    {
        $terminal = array_values(array_filter(
            WorkflowStatus::cases(),
            fn (WorkflowStatus $s): bool => $s->isTerminal()
        ));

        $this->assertSame([
            WorkflowStatus::Succeeded,
            WorkflowStatus::Warning,
            WorkflowStatus::Failed,
        ], $terminal);
    }

    #[Test]
    public function a_record_starts_not_started(): void
    {
        $this->assertSame(WorkflowStatus::NotStarted, WorkflowStatus::default());
    }

    #[Test]
    public function the_badge_palette_is_the_six_the_design_system_defines(): void
    {
        $this->assertSame(
            ['neutral', 'info', 'success', 'warning', 'danger', 'violet'],
            array_map(fn (BadgeRole $r): string => $r->value, BadgeRole::cases())
        );
    }

    #[Test]
    public function the_neutral_badge_uses_the_base_class_with_no_modifier(): void
    {
        // The stylesheet makes neutral the default appearance of .badge; a
        // .badge-neutral modifier does not exist and must not be emitted.
        $this->assertSame('badge', BadgeRole::Neutral->cssClass());
        $this->assertSame('badge badge-violet', BadgeRole::Violet->cssClass());
    }
}
