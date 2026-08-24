<?php

declare(strict_types=1);

namespace App\Modules\Governance\Services;

use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Governance\Enums\ExceptionStatus;
use App\Modules\Governance\Exceptions\GovernanceStorageNotInitialised;
use App\Modules\Governance\Models\SovereigntyException;
use App\Modules\Governance\Support\GovernanceStorage;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Requesting, approving, rejecting and revoking sovereignty exceptions. ADM-016.
 *
 * SEPARATION OF DUTIES IS ENFORCED HERE, not by the tier split.
 *
 * The tiers say a System Administrator approves and an Administrator requests.
 * That alone permits a System Administrator to approve their own request, since
 * they hold both permissions - and a control where the person asking is the
 * person agreeing is not a control. `assertNotTheRequester()` is what actually
 * prevents it, and it lives in the SERVICE so a console command or a future API
 * meets the same refusal a form does.
 *
 * NOTHING HERE EVER WRITES TO `data_sovereignty_profiles`. An exception that
 * edited the approved profile would make the approved position a lie. The
 * profile is read to record WHICH version the exception departs from, and never
 * modified.
 *
 * EXPIRY HAPPENS BY ITSELF. There is no `expire()` method, no job and no sweep.
 * `SovereigntyException::isInForce()` combines the stored status with today's
 * date, so an exception lapses at midnight with nothing needing to run
 * (SEC-DEC-069). The `.expired` audit action exists for the day a scheduler is
 * approved and something wants to announce it; nothing writes it today, and the
 * catalogue says so rather than leaving a reader to wonder why no such row
 * appears.
 */
class SovereigntyExceptions
{
    public function __construct(
        private readonly GovernanceStorage $storage,
        private readonly AuditLogger $audit,
        private readonly SovereigntyProfiles $profiles,
    ) {}

    /**
     * Every exception, newest first.
     *
     * @return Collection<int, SovereigntyException>
     */
    public function all(): Collection
    {
        if (! $this->storage->exceptionsAreReady()) {
            return collect();
        }

        return SovereigntyException::query()
            ->with(['requestedBy', 'decidedBy', 'revokedBy'])
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Only the exceptions actually permitting something today.
     *
     * What the sovereignty profile screen overlays, and what a reader needs to
     * see beside the approved position.
     *
     * @return Collection<int, SovereigntyException>
     */
    public function inForce(): Collection
    {
        if (! $this->storage->exceptionsAreReady()) {
            return collect();
        }

        return SovereigntyException::query()->inForce()->orderBy('ends_on')->get();
    }

    /**
     * Approved exceptions lapsing within the horizon.
     *
     * @return Collection<int, SovereigntyException>
     */
    public function expiringWithin(int $days = 30): Collection
    {
        return $this->inForce()->filter(
            static fn (SovereigntyException $e): bool => $e->daysRemaining() <= $days
        )->values();
    }

    public function awaitingDecision(): int
    {
        if (! $this->storage->exceptionsAreReady()) {
            return 0;
        }

        return SovereigntyException::query()->awaitingDecision()->count();
    }

    public function find(int $id): ?SovereigntyException
    {
        if (! $this->storage->exceptionsAreReady()) {
            return null;
        }

        /* The organisation scope is global on this model, so an id belonging to
         * another organisation simply does not resolve. */
        return SovereigntyException::query()->find($id);
    }

    /**
     * Raise a request. It permits nothing until somebody approves it.
     *
     * @param  array<string, mixed>  $values
     */
    public function request(array $values, User $actor): SovereigntyException
    {
        if (! $this->storage->exceptionsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The sovereignty exception');
        }

        $exception = new SovereigntyException;

        $exception->fill($values);
        $exception->forceFill([
            'status' => ExceptionStatus::Requested,
            /*
             * Which approved position this departs from, captured now. Comparing
             * against whatever is current later would misread an exception
             * raised against version 1 as though it had been raised against
             * version 3.
             */
            'data_sovereignty_profile_id' => $this->profiles->inForce()?->getKey(),
            'requested_by_user_id' => $actor->getKey(),
            'requested_at' => now()->utc(),
            'created_by_user_id' => $actor->getKey(),
            'updated_by_user_id' => $actor->getKey(),
        ]);

        $exception->save();

        $this->audit->record(
            action: 'governance.sovereignty_exception.requested',
            module: 'Governance',
            resourceType: 'sovereignty_exception',
            resourceId: $exception->getKey(),
            after: $this->summarise($exception),
            reason: $exception->justification,
        );

        return $exception;
    }

    /**
     * Approve it. From now until its end date, it permits what it describes.
     */
    public function approve(SovereigntyException $exception, User $actor, string $note): SovereigntyException
    {
        $this->assertDecidable($exception, $actor, $note);

        $exception->forceFill([
            'status' => ExceptionStatus::Approved,
            'decided_by_user_id' => $actor->getKey(),
            'decided_at' => now()->utc(),
            'decision_note' => $note,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'governance.sovereignty_exception.approved',
            module: 'Governance',
            resourceType: 'sovereignty_exception',
            resourceId: $exception->getKey(),
            after: $this->summarise($exception->refresh()),
            reason: $note,
        );

        return $exception;
    }

    /**
     * Refuse it. It never permitted anything and now never will.
     */
    public function reject(SovereigntyException $exception, User $actor, string $note): SovereigntyException
    {
        $this->assertDecidable($exception, $actor, $note);

        $exception->forceFill([
            'status' => ExceptionStatus::Rejected,
            'decided_by_user_id' => $actor->getKey(),
            'decided_at' => now()->utc(),
            'decision_note' => $note,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'governance.sovereignty_exception.rejected',
            module: 'Governance',
            resourceType: 'sovereignty_exception',
            resourceId: $exception->getKey(),
            after: $this->summarise($exception->refresh()),
            reason: $note,
        );

        return $exception;
    }

    /**
     * End it now, before its end date.
     *
     * A DIFFERENT ACT FROM REJECTION. Rejecting refuses something that was never
     * in force; revoking ends something that was. The trail has to be able to
     * tell them apart, because "we allowed this and then stopped" and "we never
     * allowed this" are different facts about the same request.
     *
     * Takes effect immediately: `isInForce()` reads the status, so the next
     * request sees it gone. Nothing has to run.
     */
    public function revoke(SovereigntyException $exception, User $actor, string $reason): SovereigntyException
    {
        if (! $this->storage->exceptionsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The sovereignty exception');
        }

        if ($exception->status !== ExceptionStatus::Approved) {
            throw new RuntimeException('Only an approved exception can be revoked.');
        }

        if (trim($reason) === '') {
            throw new RuntimeException('Revoking a sovereignty exception requires a stated reason.');
        }

        $exception->forceFill([
            'status' => ExceptionStatus::Revoked,
            'revoked_by_user_id' => $actor->getKey(),
            'revoked_at' => now()->utc(),
            'revocation_reason' => $reason,
            'updated_by_user_id' => $actor->getKey(),
        ])->save();

        $this->audit->record(
            action: 'governance.sovereignty_exception.revoked',
            module: 'Governance',
            resourceType: 'sovereignty_exception',
            resourceId: $exception->getKey(),
            after: $this->summarise($exception->refresh()),
            reason: $reason,
        );

        return $exception;
    }

    /**
     * The guards every decision shares.
     *
     * @throws RuntimeException
     */
    private function assertDecidable(SovereigntyException $exception, User $actor, string $note): void
    {
        if (! $this->storage->exceptionsAreReady()) {
            throw GovernanceStorageNotInitialised::forWrite('The sovereignty exception');
        }

        if ($exception->status->isDecided()) {
            throw new RuntimeException(
                'This exception has already been decided. Its outcome is '
                .$exception->status->label().'.'
            );
        }

        if (trim($note) === '') {
            throw new RuntimeException('Deciding a sovereignty exception requires a stated reason.');
        }

        $this->assertNotTheRequester($exception, $actor);
    }

    /**
     * A requester never approves their own request.
     *
     * THE CONTROL THE TIER SPLIT CANNOT EXPRESS. A System Administrator holds
     * both `.request` and `.approve`, so nothing in the permission model stops
     * them deciding their own request - and a person agreeing with themselves is
     * not an approval. Decision D13, SEC-DEC-067.
     *
     * Checked against the recorded requester rather than against a tier, so it
     * holds even if the same person later changes role.
     */
    private function assertNotTheRequester(SovereigntyException $exception, User $actor): void
    {
        if ($exception->requested_by_user_id !== null
            && (int) $exception->requested_by_user_id === (int) $actor->getKey()) {
            throw new RuntimeException(
                'You raised this exception, so you cannot decide it. Somebody else with the '
                .'authority to approve must review it.'
            );
        }
    }

    /**
     * A summary for the audit trail.
     *
     * Every key was checked against `Redaction::isSensitiveKey()`. None matches.
     * `requested_geography` is used rather than `authorised_geography`, because
     * `auth` is a matched fragment and the value would be stored as
     * "[redacted]" for exactly the change an auditor came to read. SEC-DEC-044.
     *
     * @return array<string, mixed>
     */
    private function summarise(SovereigntyException $exception): array
    {
        return [
            'title' => $exception->title,
            'aspect' => $exception->aspect,
            'requested_geography' => $exception->requested_geography,
            'starts_on' => $exception->starts_on?->toDateString(),
            'ends_on' => $exception->ends_on->toDateString(),
            'state' => $exception->status->value,
            'in_force' => $exception->isInForce(),
            'departs_from_profile_version' => $exception->profile?->version,
        ];
    }
}
