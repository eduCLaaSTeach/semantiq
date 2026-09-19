<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Access\Support\RoleCode;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditWriter;
use App\Modules\People\Services\UserDirectoryService;
use App\Modules\People\Support\PeopleViolation;
use App\Modules\Platform\Security\SecurityEventLogger;
use App\Modules\Reviews\Services\ReviewDecisionService;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewViolation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\Support\ReviewFactory;
use Tests\TestCase;

/**
 * A19. REFUSAL EVIDENCE SURVIVES THE ROLLBACK OF THE THING IT REFUSED.
 *
 * THE CASE THE FIRST DESIGN WOULD HAVE LOST. ReviewDecisionService records
 * access.review.refused and then THROWS; UserDirectoryService does the same for
 * a duplicate identity. Both run inside DB::transaction, so evidence that
 * simply joined the caller's transaction would vanish with the refusal it was
 * recording - and a refusal that leaves no trace is exactly the attempt
 * somebody wanted hidden.
 */
final class AuditRefusalDurabilityTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private ReviewFactory $reviews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->reviews = new ReviewFactory;
    }

    /**
     * Mutation: class access.review.refused as a StateChange, so its evidence
     * joins the transaction. The row disappears and this fails.
     */
    public function test_a_refused_review_leaves_evidence_after_its_transaction_unwinds(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);
        $subject = $this->make->user($organisation);
        $assignment = $this->access->assignment($subject, RoleCode::OrganisationAdministrator, $organisation);
        $item = $this->reviews->privilegedItem($this->reviews->cycle($actor), $assignment);

        // Already decided, so the next decision refuses.
        $item->forceFill(['state' => 'retained', 'decided_at' => now()])->save();

        AuditEvent::query()->delete();

        try {
            app(ReviewDecisionService::class)->decide($item->fresh(), ReviewDecision::Revoke, $actor);
            $this->fail('A terminal review was decided twice.');
        } catch (ReviewViolation) {
            // Expected.
        }

        $row = AuditEvent::query()->where('event', SecurityEventLogger::REVIEW_REFUSED)->first();

        $this->assertNotNull($row, 'The refusal vanished with the transaction it refused.');
        $this->assertSame('already_decided', $row->reason);
        $this->assertSame($actor->id, (int) $row->actor_user_id);
        $this->assertSame($organisation->id, (int) $row->organisation_id);
    }

    /**
     * The same for P1-03, whose refusal also throws from inside a transaction -
     * and which now names the administrator who attempted it.
     *
     * Mutation: remove the actor from the USER_PROVISION_REFUSED context. The
     * row then says nobody attempted anything.
     */
    public function test_a_refused_provisioning_leaves_evidence_naming_the_administrator(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $existing = $this->make->user($organisation);

        // The duplicate guard matches on identity AND tenant, so the fixture's
        // tenant has to be the configured one - otherwise provisioning
        // succeeds and this case tests nothing.
        config(['identity.microsoft.tenant_id' => $existing->tenant_id]);

        AuditEvent::query()->delete();

        try {
            app(UserDirectoryService::class)->provision($organisation, [
                'object_id' => $existing->external_subject,
                'email' => $existing->email,
                'display_name' => $existing->display_name,
            ], $admin);
            $this->fail('A duplicate identity was provisioned.');
        } catch (PeopleViolation) {
            // Expected.
        }

        $row = AuditEvent::query()->where('event', SecurityEventLogger::USER_PROVISION_REFUSED)->first();

        $this->assertNotNull($row, 'A refused provisioning left no evidence.');
        $this->assertSame($admin->id, (int) $row->actor_user_id, 'The refusal named nobody.');
    }

    /**
     * AND IT IS NOT WRITTEN TWICE. StepUpService records a refusal inside a
     * transaction that then COMMITS; an unconditional re-write would record it
     * a second time, and a duplicated refusal is a falsified count.
     *
     * Mutation: drop the existence check from recoverRolledBack(), or re-write
     * on commit as well.
     */
    public function test_a_refusal_inside_a_committing_transaction_is_recorded_once(): void
    {
        $organisation = $this->make->organisation();
        $actor = $this->make->user($organisation, administrator: true);

        AuditEvent::query()->delete();

        DB::transaction(function () use ($actor, $organisation): void {
            app(SecurityEventLogger::class)->record(SecurityEventLogger::STEP_UP_REFUSED, [
                'user_id' => $actor->id,
                'organisation_id' => $organisation->id,
                'result' => 'refused',
                'reason' => 'replayed',
            ]);
        });

        $this->assertSame(
            1,
            AuditEvent::query()->where('event', SecurityEventLogger::STEP_UP_REFUSED)->count(),
            'One refusal was recorded twice.'
        );

        /*
         * AND THE RECOVERY IS IDEMPOTENT. Running it against a row that
         * survived must not write a second one - which is the whole reason the
         * re-write is conditional rather than unconditional.
         */
        app(AuditWriter::class)->recoverRolledBack();

        $this->assertSame(
            1,
            AuditEvent::query()->where('event', SecurityEventLogger::STEP_UP_REFUSED)->count(),
            'Recovery duplicated a refusal that had not been rolled back.'
        );
    }
}
