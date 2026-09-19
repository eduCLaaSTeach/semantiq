<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Modules\Audit\Models\AuditChainHead;
use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Audit\Services\AuditChainVerifier;
use App\Modules\Audit\Services\AuditHash;
use App\Modules\Platform\Security\SecurityEventLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EmitsEvidence;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * A8. THE CHAIN DETECTS ALTERATION AND REMOVAL - AND TELLS THEM APART.
 *
 * "Broken" is not a useful answer to somebody deciding what happened, and a
 * verifier that checked only row_hash would PASS a deletion, which is the case
 * that matters most.
 */
final class AuditChainTest extends TestCase
{
    use EmitsEvidence;
    use RefreshDatabase;

    private OrganisationFactory $make;

    protected function setUp(): void
    {
        parent::setUp();
        $this->make = new OrganisationFactory;
    }

    /** An untouched chain verifies. The non-vacuous half. */
    public function test_an_untouched_chain_is_intact(): void
    {
        $this->threeEvents();

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertTrue($result['intact'], 'A chain nobody touched did not verify.');
        $this->assertSame(3, $result['checked']);
    }

    /**
     * A field edited in place.
     *
     * Mutation: verify only the sequence and the previous_hash. This then
     * passes, and an edited outcome goes unnoticed.
     */
    public function test_an_altered_row_is_reported(): void
    {
        $this->threeEvents();

        // Straight past the model, the way somebody with database access would.
        DB::table('audit_events')->where('sequence', 2)->update(['outcome' => 'succeeded']);

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['intact']);
        $this->assertSame('altered', $result['finding']);
        $this->assertSame(2, $result['sequence']);
    }

    /**
     * A ROW DELETED. The case a row-hash-only verifier passes.
     *
     * Mutation: drop the previous_hash comparison from the verifier. Each
     * surviving row still hashes correctly, so the deletion is invisible.
     */
    public function test_a_removed_row_is_reported(): void
    {
        $this->threeEvents();

        DB::table('audit_events')->where('sequence', 2)->delete();

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['intact']);
        // The SEQUENCE gap is what names this one. Accepting any finding would
        // let the head comparison satisfy it, which is a different guard.
        $this->assertSame('missing', $result['finding']);
        $this->assertSame(2, $result['sequence']);
    }

    /**
     * A ROW DELETED FROM THE END. Nothing above it breaks, so without the head
     * comparison the newest evidence could be dropped silently - which is
     * exactly the evidence somebody would want gone.
     *
     * Mutation: drop the head/last-row comparison.
     */
    public function test_a_row_removed_from_the_end_is_reported(): void
    {
        $this->threeEvents();

        DB::table('audit_events')->where('sequence', 3)->delete();

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['intact'], 'The newest record was removed and nothing noticed.');
    }

    /**
     * THE FORGERY A ROW-HASH CHECK CANNOT SEE, and the case that exposed a
     * weakness in this very file.
     *
     * Mutation m5 removed BOTH the predecessor comparison and the sequence
     * check, and test_a_removed_row_is_reported still passed - because the
     * head/last-row comparison caught the deletion instead. It was reporting
     * safety it was not measuring, which is CLAUDE.md §2 exactly.
     *
     * Somebody with database access does not merely delete: they EDIT a field
     * and recompute that row's own hash, which is easy. Only the link to the
     * NEXT row survives that, because they would have to recompute every later
     * row too.
     *
     * Mutation: remove the previous_hash comparison. Nothing else in this file
     * catches this.
     */
    public function test_a_forged_row_with_a_recomputed_hash_is_reported(): void
    {
        $this->threeEvents();

        $forged = AuditEvent::query()->where('sequence', 2)->sole();

        $attributes = $forged->getAttributes();
        $attributes['outcome'] = 'succeeded';

        // Exactly what a forger with database access would do: change the
        // field, then make the row's own hash agree with it again.
        DB::table('audit_events')->where('sequence', 2)->update([
            'outcome' => 'succeeded',
            'row_hash' => AuditHash::of((string) $forged->previous_hash, $attributes),
        ]);

        $result = app(AuditChainVerifier::class)->verify();

        $this->assertFalse($result['intact'], 'A row was edited and its hash recomputed, and nothing noticed.');
        $this->assertSame('removed', $result['finding'], 'The break was not found at the link to the next row.');
        $this->assertSame(3, $result['sequence']);
    }

    /**
     * A22 / the hash-coverage guard. EVERY stored column that carries meaning
     * is hashed.
     *
     * Mutation: add a column to the table and not to AuditHash::FIELDS. It is
     * then a field nobody can prove was not edited - a silent hole in the one
     * guarantee the chain provides.
     */
    public function test_every_meaningful_column_is_hashed(): void
    {
        $columns = Schema::getColumnListing('audit_events');

        // Not hashed, and each for a stated reason: the surrogate key, the
        // chain's own fields, and Eloquent's timestamps - which are written by
        // the framework and carry no evidence of their own.
        $exempt = ['id', 'previous_hash', 'row_hash', 'created_at', 'updated_at'];

        $shouldHash = array_values(array_diff($columns, $exempt));
        $hashed = AuditHash::FIELDS;

        sort($shouldHash);
        sort($hashed);

        $this->assertSame($shouldHash, $hashed, 'A column exists that the chain does not cover.');
    }

    /** The sequence is unique, so a removal cannot be papered over by an insert. */
    public function test_the_sequence_is_unique(): void
    {
        $this->threeEvents();

        $this->assertSame([1, 2, 3], AuditEvent::query()->orderBy('sequence')->pluck('sequence')->all());
        $this->assertSame(3, AuditChainHead::query()->find(AuditChainHead::ID)->sequence);
    }

    private function threeEvents(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);

        AuditEvent::query()->delete();
        AuditChainHead::query()->whereKey(AuditChainHead::ID)->update([
            'sequence' => 0,
            'row_hash' => hash('sha256', 'semantiq.audit.genesis|'
                .AuditChainHead::query()->find(AuditChainHead::ID)->started_at->toIso8601String()),
        ]);

        foreach (['succeeded', 'signed_out', 'succeeded'] as $i => $result) {
            $this->emit(
                $i === 1 ? SecurityEventLogger::LOGOUT : SecurityEventLogger::LOGIN_SUCCEEDED,
                [
                    'provider' => 'microsoft',
                    'subject' => $user->external_subject,
                    'tenant' => $user->tenant_id,
                    'user_id' => $user->id,
                    'organisation_id' => $organisation->id,
                    'result' => $result,
                ],
            );
        }
    }
}
