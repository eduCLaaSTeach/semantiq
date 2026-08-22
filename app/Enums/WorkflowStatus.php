<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The ten-state standard status model from SRS section 18.1.
 *
 * One vocabulary for every workflow, readiness check, validation run and item
 * in the product. The SRS defines these states once precisely so that a person
 * who learns what "Action Required" means on the Fabric readiness screen reads
 * it the same way on a deployment, and so an operator triaging a failure knows
 * without asking whether anything is still moving.
 *
 * Three distinctions in here are easy to collapse by accident and are worth
 * naming, because collapsing them is what makes a status board useless:
 *
 *  - Action Required and Approval Required both wait on a person, but on
 *    different people for different reasons: one is missing work, the other is
 *    withheld authority.
 *  - Warning is not Failed. The operation completed and the target works; a
 *    risk or an optional prerequisite is outstanding.
 *  - Drift Detected and Revalidation Required are not failures either. Something
 *    outside SemantIQ changed, so recorded evidence is stale rather than wrong.
 *
 * The backing values are snake_case codes rather than the display labels,
 * because they are persisted. Relabelling a state must never require a data
 * migration, and a label is a presentation decision that can change.
 *
 * Requirement IDs: NFR-OBS-01, NFR-SUP-01. SRS section 18.1.
 */
enum WorkflowStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case ActionRequired = 'action_required';
    case ApprovalRequired = 'approval_required';
    case Ready = 'ready';
    case Succeeded = 'succeeded';
    case Warning = 'warning';
    case Failed = 'failed';
    case DriftDetected = 'drift_detected';
    case RevalidationRequired = 'revalidation_required';

    /**
     * The state a record holds before anything has been attempted.
     */
    public static function default(): self
    {
        return self::NotStarted;
    }

    /**
     * The label shown to a person, exactly as SRS section 18.1 words it.
     */
    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::ActionRequired => 'Action Required',
            self::ApprovalRequired => 'Approval Required',
            self::Ready => 'Ready',
            self::Succeeded => 'Succeeded',
            self::Warning => 'Warning',
            self::Failed => 'Failed',
            self::DriftDetected => 'Drift Detected',
            self::RevalidationRequired => 'Revalidation Required',
        };
    }

    /**
     * The plain-language meaning, for tooltips and the help centre.
     */
    public function meaning(): string
    {
        return match ($this) {
            self::NotStarted => 'No configuration exists.',
            self::InProgress => 'User entered draft or workflow running.',
            self::ActionRequired => 'Manual or administrator prerequisite missing.',
            self::ApprovalRequired => 'Privileged or release operation waiting approval.',
            self::Ready => 'Prerequisites satisfied; action can run.',
            self::Succeeded => 'Target verified.',
            self::Warning => 'Function works but a risk or optional prerequisite exists.',
            self::Failed => 'Operation did not complete.',
            self::DriftDetected => 'External change differs from the SemantIQ recorded configuration.',
            self::RevalidationRequired => 'An upstream change invalidates prior tests.',
        };
    }

    /**
     * The action the SRS expects a screen to offer in this state.
     *
     * Held here rather than per screen so that the same state never offers
     * "Retry" in one place and "Try again" in another. A screen may present it
     * as a button, a link or a menu entry; the wording is not the screen's to
     * invent.
     */
    public function uiAction(): string
    {
        return match ($this) {
            self::NotStarted => 'Start Setup',
            self::InProgress => 'View Progress',
            self::ActionRequired => 'Open Help',
            self::ApprovalRequired => 'Review and Approve',
            self::Ready => 'Run',
            self::Succeeded => 'Continue',
            self::Warning => 'Review Warning',
            self::Failed => 'Retry',
            self::DriftDetected => 'Compare',
            self::RevalidationRequired => 'Run Validation',
        };
    }

    /**
     * Which of the design system's six badge roles this state renders as.
     *
     * Ten states into six colours is lossy on purpose, and the losses are chosen
     * rather than accidental:
     *
     *  - Approval Required takes violet, the one role no other state uses, so
     *    "someone must authorise this" is visually distinct from "someone must
     *    do work". Those two queues belong to different people.
     *  - Ready shares info with In Progress: both mean the system is fine and
     *    nobody is blocked.
     *  - Drift Detected and Revalidation Required share warning with Warning
     *    itself. All three mean "works, but the evidence needs attention", which
     *    is precisely what the warning role is for. Reaching for danger here
     *    would train people to ignore danger.
     */
    public function badge(): BadgeRole
    {
        return match ($this) {
            self::NotStarted => BadgeRole::Neutral,
            self::InProgress, self::Ready => BadgeRole::Info,
            self::ApprovalRequired => BadgeRole::Violet,
            self::ActionRequired, self::Warning,
            self::DriftDetected, self::RevalidationRequired => BadgeRole::Warning,
            self::Succeeded => BadgeRole::Success,
            self::Failed => BadgeRole::Danger,
        };
    }

    /**
     * Whether the system is still working on this by itself.
     *
     * Only In Progress is. Everything else, including the states that wait on a
     * person, has stopped moving until something outside the run happens. The
     * orchestrator uses this to decide whether re-queueing a run makes sense.
     */
    public function isRunning(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Whether a person has to do something before this can advance.
     *
     * Drives the "needs you" counts on the dashboard. Note that Failed is not
     * included: a failure needs triage, which may or may not be this person's,
     * and inflating an action queue with everything red makes the queue useless.
     */
    public function awaitsPerson(): bool
    {
        return match ($this) {
            self::ActionRequired, self::ApprovalRequired,
            self::DriftDetected, self::RevalidationRequired => true,
            default => false,
        };
    }

    /**
     * Whether this state ends a run.
     *
     * A terminal state is one the orchestrator will not move on from. The run
     * may still be superseded by a new run, which is a different record.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Warning, self::Failed => true,
            default => false,
        };
    }
}
