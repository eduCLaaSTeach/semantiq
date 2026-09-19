<?php

declare(strict_types=1);

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Platform\Security\EvidenceNotRecorded;
use App\Modules\Platform\Security\EvidenceRecorder;
use App\Modules\Security\Catalogue\ActorSource;
use App\Modules\Security\Catalogue\EventCatalogue;
use App\Modules\Security\Catalogue\EventSemantics;
use App\Modules\Security\Catalogue\OrganisationSource;
use App\Modules\Security\Catalogue\OutcomeClass;
use App\Modules\Security\Catalogue\SubjectSource;
use App\Modules\Security\Catalogue\TargetSource;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;
use Throwable;

/**
 * P1-08's half of SecurityEventLogger. D-95, D-96, D-111.
 *
 * EVERY MEANING COMES FROM THE CATALOGUE. This class reads no convention and
 * infers nothing: EventCatalogue::semanticsFor() says where the actor is, where
 * the subject is, what the target is, which organisation owns the evidence and
 * what must happen if it cannot be written. An event with no declared semantics
 * throws, and the completeness test makes that unreachable by failing the build
 * first.
 *
 * THREE WRITE MODES, because a success and a refusal need OPPOSITE behaviour:
 *
 *   StateChange  joins the caller's transaction. A failed insert rolls the
 *                change back with it - fail closed, D-111.
 *   Refusal      must SURVIVE the caller's transaction, which is about to roll
 *                back. ReviewDecisionService, UserDirectoryService and
 *                StepUpService each record a refusal and THEN throw; an insert
 *                that joined would vanish with the refusal it was recording,
 *                and a refusal that leaves no trace is precisely the attempt
 *                somebody wanted hidden.
 *   BestEffort   sign-out and session expiry. Never blocks.
 *
 * HOW A REFUSAL SURVIVES ON ONE CONNECTION. A second connection is not an
 * option: the test database is SQLite in-memory, where a second connection is a
 * second, EMPTY database - so a "write it elsewhere" design would have to be
 * trusted rather than proven, and an untestable guarantee is the kind this
 * project keeps discovering was never true.
 *
 * So a refusal is written immediately, AND REMEMBERED while a transaction is
 * open. If that transaction rolls back - taking the row with it - the row is
 * written again once the rollback has happened. The re-write is conditional on
 * the row actually having gone, so a refusal recorded inside a transaction that
 * COMMITS (StepUpService does exactly that) is never duplicated.
 *
 * THE LIMIT, STATED: if the process dies between the rollback and the re-write,
 * that refusal is lost, and Log::critical is the only trace. What cannot happen
 * is the case this exists for - a refusal silently erased by the rollback of
 * the thing it refused.
 */
final class AuditWriter implements EvidenceRecorder
{
    /**
     * Refusals written while a transaction was open, and therefore at risk of
     * being rolled back with it.
     *
     * @var list<array<string, mixed>>
     */
    private array $fragile = [];

    /**
     * THE ATOMICITY GUARD. D-111 is a claim about a TRANSACTION, so this checks
     * there is one.
     *
     * A state change recorded outside a transaction cannot fail closed: by the
     * time the insert fails, the change it evidences has already committed and
     * there is nothing left to roll back. The DESIGN said mutation and evidence
     * share a transaction; five emitters did not, and nothing would have
     * noticed - OrganisationService::updateProfile, UserDirectoryService::
     * reactivate, and three GroupService methods all saved first and recorded
     * afterwards, outside any transaction.
     *
     * So the requirement is ENFORCED rather than documented. An emitter added
     * later outside the boundary raises here the first time any test exercises
     * it, instead of shipping as a silent hole in fail-closed.
     *
     * THE BASELINE EXISTS FOR THE TEST HARNESS AND NOTHING ELSE. In production
     * it is 0 and never moves. RefreshDatabase wraps every test in its own
     * transaction, so a test's baseline is 1 - and without that the guard would
     * be satisfied by the harness and would catch nothing, which is precisely
     * the vacuous guard this project keeps finding.
     */
    public static int $baselineTransactionLevel = 0;

    public function record(string $event, array $context): void
    {
        $semantics = EventCatalogue::semanticsFor($event);

        if ($semantics->outcome === OutcomeClass::StateChange
            && DB::transactionLevel() <= self::$baselineTransactionLevel) {
            throw new LogicException(
                "The state change evidenced by [{$event}] is not inside a database transaction, "
                .'so a failed audit write could not roll it back. Wrap the whole operation in '
                .'DB::transaction() - D-111 requires the change and its evidence to commit or '
                .'fail together.'
            );
        }

        $row = $this->row($event, $semantics, $context);

        // BOTH fail closed. They differ only in HOW the change is prevented
        // from surviving a failure - a shared transaction, or being written
        // before the change happens at all.
        if ($semantics->outcome === OutcomeClass::StateChange
            || $semantics->outcome === OutcomeClass::StateChangeRecordedFirst) {
            $this->writeOrFail($row, $event);

            return;
        }

        $this->writeQuietly($row, $event);

        if (DB::transactionLevel() > 0) {
            // Somebody's transaction is open and may unwind, taking this with
            // it. Remembered so it can be written again once it has.
            $this->fragile[] = $row;
        }
    }

    /**
     * A transaction ended. Anything it took with it is written again.
     *
     * CONDITIONAL ON THE ROW HAVING GONE. StepUpService records a refusal
     * inside a transaction that then COMMITS, so an unconditional re-write
     * would record that refusal twice - and a duplicated refusal is a
     * falsified count, which is its own kind of lying.
     *
     * occurred_at carries microseconds and is set here rather than by the
     * database, so it identifies the row without needing its id back.
     */
    public function recoverRolledBack(): void
    {
        $rows = $this->fragile;
        $this->fragile = [];

        foreach ($rows as $row) {
            $survived = AuditEvent::query()
                ->where('event', $row['event'])
                ->where('occurred_at', $row['occurred_at'])
                ->exists();

            if (! $survived) {
                $this->writeQuietly($row, (string) $row['event']);
            }
        }
    }

    /** Everything committed; nothing is at risk. */
    public function forgetFragile(): void
    {
        $this->fragile = [];
    }

    public function fragileCount(): int
    {
        return count($this->fragile);
    }

    /** @param  array<string, mixed>  $row */
    private function writeOrFail(array $row, string $event): void
    {
        try {
            $this->append($row);
        } catch (Throwable $e) {
            // Operator diagnostics only. NOT evidence - nothing reads it back,
            // and D-111 is explicit that it does not substitute for one.
            Log::critical('audit.persist.failed', [
                'event' => $event,
                'mode' => 'state_change',
                'error' => $e->getMessage(),
            ]);

            throw EvidenceNotRecorded::make();
        }
    }

    /** @param  array<string, mixed>  $row */
    private function writeQuietly(array $row, string $event): void
    {
        try {
            $this->append($row);
        } catch (Throwable $e) {
            /*
             * THE ACTION STAYS AS IT WAS. Failing closed may turn a success
             * into a failure; it must never turn a refusal into anything else,
             * and it must never prevent somebody signing out.
             */
            Log::critical('audit.persist.failed', [
                'event' => $event,
                'mode' => 'refusal_or_best_effort',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The chain append.
     *
     * THE HEAD ROW IS LOCKED, AND IT IS ALWAYS THE LAST LOCK TAKEN - after the
     * administrator-set lock, the review-item lock and the entitlement lock.
     * One deterministic global ordering, so audit can never be the second half
     * of a lock cycle.
     *
     * The sequence comes from the LOCKED head, never from MAX(sequence)+1: two
     * writers reading a maximum can read the same one, and the chain forks.
     *
     * @param  array<string, mixed>  $row
     */
    private function append(array $row): void
    {
        DB::transaction(function () use ($row): void {
            /** @var AuditChainHead $head */
            $head = AuditChainHead::query()
                ->whereKey(AuditChainHead::ID)
                ->lockForUpdate()
                ->firstOrFail();

            $row['sequence'] = $head->sequence + 1;
            $row['previous_hash'] = $head->row_hash;
            $row['row_hash'] = AuditHash::of($head->row_hash, $row);

            AuditEvent::query()->create($row);

            $head->forceFill(['sequence' => $row['sequence'], 'row_hash' => $row['row_hash']])->save();
        });
    }

    /**
     * Context to columns, entirely from the declared semantics.
     *
     * @param  array<string, scalar|null>  $context
     * @return array<string, mixed>
     */
    private function row(string $event, EventSemantics $semantics, array $context): array
    {
        $actorUserId = match ($semantics->actor) {
            ActorSource::UserId => $context['user_id'] ?? null,
            ActorSource::RelatedId => $context['related_id'] ?? null,
            ActorSource::ExternalSubject, ActorSource::System => null,
        };

        /*
         * THE ACTOR TYPE FOLLOWS THE DECLARATION, and degrades to `system`
         * rather than to a guess. An event that declares UserId but arrives
         * without one is honestly actor-less; NO LOOKUP is attempted to produce
         * a person, because an unknown actor rendered as somebody's name is
         * evidence that lies.
         */
        $actorType = match (true) {
            $semantics->actor === ActorSource::ExternalSubject => 'external_subject',
            $semantics->actor === ActorSource::System => 'system',
            $actorUserId !== null => 'person',
            default => 'system',
        };

        $subjectUserId = match ($semantics->subject) {
            SubjectSource::UserId => $context['user_id'] ?? null,
            SubjectSource::RelatedId => $context['related_id'] ?? null,
            SubjectSource::EntityId => $context['entity_id'] ?? null,
            SubjectSource::ExternalSubject, SubjectSource::None => null,
        };

        $external = $semantics->actor === ActorSource::ExternalSubject
            || $semantics->subject === SubjectSource::ExternalSubject;

        return [
            // Exact, and the server's. Never client-supplied.
            'occurred_at' => now()->format('Y-m-d H:i:s.u'),
            'event' => $event,
            'category' => $semantics->category->value,

            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId === null ? null : (int) $actorUserId,
            // Platform-sensitive. Stored, and projected to System
            // Administrators only - AuditProjection.
            'actor_subject' => $external ? ($context['subject'] ?? null) : null,
            'actor_tenant' => $external ? ($context['tenant'] ?? null) : null,
            'actor_provider' => $context['provider'] ?? null,

            'organisation_id' => $semantics->organisation === OrganisationSource::Context
                ? ($context['organisation_id'] ?? null)
                : null,

            'subject_user_id' => $subjectUserId === null ? null : (int) $subjectUserId,
            'subject_external' => $semantics->subject === SubjectSource::ExternalSubject
                ? ($context['subject'] ?? null)
                : null,

            'target_type' => $semantics->target === TargetSource::EntityTypeAndId
                ? ($context['entity_type'] ?? $semantics->targetType)
                : null,
            'target_id' => $semantics->target === TargetSource::EntityTypeAndId && isset($context['entity_id'])
                ? (int) $context['entity_id']
                : null,

            'outcome' => $context['result'] ?? null,
            'reason' => $context['reason'] ?? null,

            'role' => $context['role'] ?? null,
            'domain_id' => isset($context['domain_id']) ? (int) $context['domain_id'] : null,
            'scope' => $context['scope'] ?? null,
            'sensitivity' => $context['sensitivity'] ?? null,

            'context_expires_at' => isset($context['expires_at'])
                ? CarbonImmutable::parse((string) $context['expires_at'])->format('Y-m-d H:i:s.u')
                : null,
        ];
    }
}
