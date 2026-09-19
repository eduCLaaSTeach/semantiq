<?php

declare(strict_types=1);

namespace App\Modules\Audit\Projection;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Models\User;
use App\Modules\Security\Catalogue\EventCatalogue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * TWO SEPARATE QUESTIONS, NEVER ONE. D-99 and D-100.
 *
 *   1. WHICH ROWS may this viewer see?
 *   2. WHICH FIELDS of a visible row may they see?
 *
 * P1-06 conflated them once and showed an Auditor - whose whole role is reading
 * evidence - an empty screen, because it filtered the listing by who may ACT.
 * They are answered separately here for exactly that reason.
 *
 * PLATFORM ROWS ARE AN EXPLICIT BRANCH, NOT AN OMITTED CLAUSE. A row with no
 * organisation is System Administrator only; expressing that by leaving the
 * WHERE off for one viewer is how "no organisation" quietly becomes "every
 * organisation".
 */
final class AuditProjection
{
    /**
     * Narrow a listing to the rows this viewer may see.
     *
     * @param  Builder<AuditEvent>  $query
     */
    public function scopeVisible(Builder $query, User $viewer, ?int $organisationId): void
    {
        if (! $viewer->isActive()) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (! $this->isSystemAdministrator($viewer)) {
            // Own organisation ONLY. Platform evidence is not theirs.
            $query->where('organisation_id', $viewer->organisation_id);

            return;
        }

        $query->where(function (Builder $scoped) use ($organisationId): void {
            $scoped->where('organisation_id', $organisationId)
                ->orWhereNull('organisation_id');
        });
    }

    /**
     * One row, as this viewer may read it.
     *
     * THE KEY SET IS INVARIANT. A withheld field is present and carries the one
     * constant sentence; it is never dropped and never null. A payload whose
     * SHAPE changed with the viewer's authority would itself be the disclosure,
     * and a null is a value somebody later sorts by, colours or prints.
     *
     * @return array<string, mixed>
     */
    public function row(AuditEvent $event, User $viewer, callable $personName): array
    {
        $privileged = $this->isSystemAdministrator($viewer);

        return [
            'id' => $event->id,
            'at' => CarbonImmutable::parse((string) $event->occurred_at)->format('j F Y, H:i'),
            'category' => $event->category->value,
            // The BUSINESS label. The dotted key never reaches a screen.
            'action' => EventCatalogue::mapping()[$event->event][1] ?? 'A recorded security event',
            'actorType' => $event->actor_type,
            'actor' => $this->actorName($event, $personName),
            /*
             * A RAW DIRECTORY IDENTIFIER IS NOT AN ANSWER TO "About whom?".
             *
             * The first version put subject_external here, and the screen read
             * "About: e7c1d2f0-outside-the-directory" - an internal identifier
             * under a business label, and a duplicate of the Directory account
             * field two columns along. The actor line already says what there
             * is to say about an outside account; the identifier belongs in the
             * one place that is labelled as an identifier and withheld from
             * viewers who may not see it.
             */
            'subject' => $event->subject_user_id === null
                ? null
                : $personName($event->subject_user_id),
            'target' => $this->target($event),
            // Suppressed when it is the same person: "Who: Priya Nair /
            // About: Priya Nair" on a sign-in is noise, and noise is what a
            // reader learns to skip past.
            'subjectIsActor' => $event->subject_user_id !== null
                && $event->subject_user_id === $event->actor_user_id,
            'outcome' => $event->outcome,
            'reason' => $event->reason,
            // PLATFORM-SENSITIVE. System Administrator only - D-100.
            'directorySubject' => $privileged ? $event->actor_subject : self::WITHHELD,
            'directoryTenant' => $privileged ? $event->actor_tenant : self::WITHHELD,
            'directoryProvider' => $privileged ? $event->actor_provider : self::WITHHELD,
        ];
    }

    public const WITHHELD = 'Managed by the platform administrator.';

    private function actorName(AuditEvent $event, callable $personName): string
    {
        return match ($event->actor_type) {
            'person' => $event->actor_user_id === null
                ? 'Not recorded'
                : $personName($event->actor_user_id),
            // NEVER a name. There is no SemantIQ account behind a refused
            // sign-in, and inventing one is evidence that lies.
            'external_subject' => 'An account outside SemantIQ',
            default => 'SemantIQ',
        };
    }

    /**
     * WHAT WAS ACTED ON, as a kind of thing - NOT as a row number.
     *
     * The id is stored, because evidence should be able to point at the exact
     * record. It is not SHOWN, because "Role assignment #12" is a database key
     * on a user-facing surface and 12 means nothing to the person reading it.
     * Who, about whom and when already tell two rows apart.
     */
    private function target(AuditEvent $event): ?string
    {
        if ($event->target_type === null) {
            return null;
        }

        return ucfirst(str_replace('_', ' ', $event->target_type));
    }

    private function isSystemAdministrator(User $viewer): bool
    {
        return RoleAssignment::query()
            ->where('user_id', $viewer->getKey())
            ->whereNull('ended_at')
            ->where('role_code', RoleCode::SystemAdministrator->value)
            ->exists();
    }
}
