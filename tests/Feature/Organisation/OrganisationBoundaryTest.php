<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Models\TeamMembership;
use App\Modules\Organisation\Services\ManagementService;
use App\Modules\Organisation\Services\MembershipService;
use App\Modules\Organisation\Services\OrganisationService;
use App\Modules\Organisation\Services\StructureService;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Http\Middleware\EnsureSessionIsCurrent;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * Negative cases 8, 9, 10, 15, 17, 18, 19, 20 and 21 - the D-16 seam and the
 * membership and management rules that depend on it.
 */
final class OrganisationBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private MembershipService $memberships;

    private ManagementService $management;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->memberships = app(MembershipService::class);
        $this->management = app(ManagementService::class);
    }

    // -- D-16 --------------------------------------------------------------

    /** The column exists, is nullable, and points at organisations. */
    public function test_the_d16_seam_is_a_nullable_organisation_foreign_key(): void
    {
        $column = collect(Schema::getColumns('users'))->firstWhere('name', 'organisation_id');

        $this->assertNotNull($column, 'users.organisation_id does not exist, so no same-organisation rule can be honest.');
        $this->assertTrue($column['nullable'], 'The D-16 column must be nullable: there is no organisation to backfill to.');

        $keys = collect(Schema::getForeignKeys('users'))
            ->firstWhere(fn (array $key): bool => $key['columns'] === ['organisation_id']);

        $this->assertNotNull($keys, 'users.organisation_id has no foreign key to organisations.');
        $this->assertSame('organisations', $keys['foreign_table']);
    }

    /**
     * Case 18. Mutation: allow a NULL organisation through.
     *
     * NULL means "not yet associated" and fails closed.
     */
    public function test_a_user_without_an_organisation_cannot_join_a_team(): void
    {
        $organisation = $this->make->organisation();
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));

        $unassociated = $this->make->user(null);

        $this->assertNull($unassociated->organisation_id);

        $this->expectViolation('user_without_organisation', fn () => $this->memberships->add(
            $team,
            $unassociated,
            $this->make->user($organisation, administrator: true)
        ));
    }

    /** Case 18, the management half. */
    public function test_a_user_without_an_organisation_cannot_enter_the_management_chain(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $associated = $this->make->user($organisation);
        $unassociated = $this->make->user(null);

        $this->expectViolation(
            'user_without_organisation',
            fn () => $this->management->setManager($unassociated, $associated, $admin)
        );

        $this->expectViolation(
            'user_without_organisation',
            fn () => $this->management->setManager($associated, $unassociated, $admin)
        );
    }

    /**
     * Case 19, and the mutation that would actually have shipped.
     *
     * The two users share an Entra tenant_id and differ in organisation_id. A
     * guard reading tenant_id would permit this - and would keep permitting it,
     * silently, until a second organisation existed in production.
     *
     * Mutation: drop the comparison, OR replace organisation_id with tenant_id.
     * Both must fail this test.
     */
    public function test_membership_across_organisations_is_refused_even_within_one_entra_tenant(): void
    {
        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $tenant = '11111111-1111-1111-1111-111111111111';

        $team = $this->make->team($this->make->department($this->make->businessUnit($ours)));
        $foreignUser = $this->make->user($theirs, tenant: $tenant);
        $admin = $this->make->user($ours, administrator: true, tenant: $tenant);

        // The precondition that makes this non-vacuous: same directory, different
        // SemantIQ organisation.
        $this->assertSame($tenant, $foreignUser->tenant_id);
        $this->assertSame($tenant, $admin->tenant_id);
        $this->assertNotSame($foreignUser->organisation_id, $team->organisation_id);

        $this->expectViolation(
            'organisation_mismatch',
            fn () => $this->memberships->add($team, $foreignUser, $admin)
        );
    }

    /** Case 19, the management half, with the same tenant precondition. */
    public function test_a_management_relationship_across_organisations_is_refused_within_one_entra_tenant(): void
    {
        $tenant = '11111111-1111-1111-1111-111111111111';

        $ours = $this->make->organisation('Ours');
        $theirs = $this->make->organisation('Theirs');

        $subject = $this->make->user($ours, tenant: $tenant);
        $foreignManager = $this->make->user($theirs, tenant: $tenant);
        $admin = $this->make->user($ours, administrator: true, tenant: $tenant);

        $this->assertSame($subject->tenant_id, $foreignManager->tenant_id);

        $this->expectViolation(
            'organisation_mismatch',
            fn () => $this->management->setManager($subject, $foreignManager, $admin)
        );
    }

    /** No code in this unit may read tenant_id. That is the substitution guard, stated directly. */
    public function test_no_organisation_code_reads_the_entra_tenant(): void
    {
        $root = __DIR__.'/../../../app/Modules/Organisation';
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        $checked = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $checked++;

            // Comments are stripped first. Several files EXPLAIN in a docblock
            // that tenant_id must never be substituted here, and a scan that
            // counted those would force the explanation to be deleted to make
            // the guard pass - which is the wrong way round.
            $this->assertStringNotContainsString(
                'tenant_id',
                $this->sourceWithoutComments($file->getPathname()),
                basename($file->getPathname()).' reads Entra tenant_id. That is a directory boundary, '
                .'not the SemantIQ organisation, and substituting it makes every same-organisation '
                .'guard pass for a reason unrelated to what it claims to check.'
            );
        }

        $this->assertGreaterThan(0, $checked, 'No Organisation source files were scanned, so this proves nothing.');
    }

    /**
     * Case 20. Mutation: skip the association.
     *
     * D-16's population rule: no seed, no backfill, no manual write. The
     * administrator who creates the Company Profile is associated with it in the
     * same transaction.
     */
    public function test_creating_the_company_profile_associates_the_administrator(): void
    {
        $admin = $this->make->user(null, administrator: true);

        $this->assertNull($admin->organisation_id, 'The precondition is that the administrator starts unassociated.');

        $organisation = app(OrganisationService::class)->createProfile(['name' => 'Acme'], $admin);

        $this->assertSame($organisation->id, $admin->fresh()->organisation_id);
    }

    /**
     * And it is the ONLY place in this unit that writes the column.
     *
     * Asserted behaviourally rather than by reading the source: a source scan
     * can only match the spelling of a write it already anticipates, and would
     * pass against any write phrased differently. This exercises every P1-01
     * service that touches a user and asserts the column did not move.
     *
     * Mutation: set organisation_id anywhere in MembershipService,
     * ManagementService or StructureService.
     */
    public function test_no_other_operation_writes_the_d16_column(): void
    {
        $organisation = $this->make->organisation();
        $other = $this->make->organisation('Other');
        $admin = $this->make->user($organisation, administrator: true);

        $unit = $this->make->businessUnit($organisation);
        $department = $this->make->department($unit);
        $team = $this->make->team($department);
        $entity = $this->make->legalEntity($organisation);

        $member = $this->make->user($organisation);
        $manager = $this->make->user($organisation);
        $unassociated = $this->make->user(null);

        $before = User::query()->orderBy('id')->pluck('organisation_id', 'id')->all();

        $structure = app(StructureService::class);
        $structure->associate($unit, $entity, $admin);
        $structure->moveDepartment($department, $this->make->businessUnit($organisation, 'Elsewhere'), $admin);

        $membership = $this->memberships->add($team, $member, $admin);
        $this->memberships->remove($membership, $admin);

        $this->management->setManager($member, $manager, $admin);
        $this->management->clearManager($member, $admin);

        // And the refusals, which must not write the column on their way out.
        try {
            $this->memberships->add($team, $unassociated, $admin);
        } catch (StructureViolation) {
            // Expected.
        }

        $this->assertSame(
            $before,
            User::query()->orderBy('id')->pluck('organisation_id', 'id')->all(),
            'A P1-01 operation other than Company Profile creation changed users.organisation_id. '
            .'D-16 gives the column exactly one writer; P1-03 provisions users later.'
        );

        $this->assertNotNull($other->id);
    }

    /**
     * Case 21. Mutation: derive any access from organisation_id.
     *
     * The D-16 counterpart to case 4. Association is not entitlement: an
     * administrator associated with the organisation is no closer to business
     * data than one who is not, because the column is never consulted to answer
     * an access question.
     */
    public function test_association_is_never_read_as_entitlement(): void
    {
        $organisation = $this->make->organisation();
        $associated = $this->make->user($organisation, administrator: true);
        $unassociated = $this->make->user(null, administrator: true);

        // Both reach the same screens, because the gate is the platform role and
        // nothing else. If organisation_id were an entitlement, these would differ.
        foreach ([$associated, $unassociated] as $admin) {
            $this->actingAsUser($admin)->get('/console/organisation')->assertOk();
        }

        // And the authorisation gate does not mention the column at all.
        $gate = file_get_contents(
            __DIR__.'/../../../app/Modules/Organisation/Http/Middleware/RequireSystemAdministrator.php'
        );

        $this->assertStringNotContainsString('organisation_id', $gate);
        $this->assertStringNotContainsString('belongsToOrganisation', $gate);
    }

    // -- Membership and management ----------------------------------------

    /** Case 9. Mutation: drop the self-manager check. */
    public function test_a_user_may_not_manage_themselves(): void
    {
        $organisation = $this->make->organisation();
        $user = $this->make->user($organisation);

        $this->expectViolation('self_manager', fn () => $this->management->setManager(
            $user,
            $user,
            $this->make->user($organisation, administrator: true)
        ));
    }

    /** Case 8. Mutation: remove the chain walk. */
    public function test_a_cycle_in_the_management_chain_is_refused(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $a = $this->make->user($organisation);
        $b = $this->make->user($organisation);
        $c = $this->make->user($organisation);

        $this->management->setManager($a, $b, $admin);
        $this->management->setManager($b, $c, $admin);

        // c reporting to a would close the loop a -> b -> c -> a.
        $this->expectViolation('management_cycle', fn () => $this->management->setManager($c, $a, $admin));
    }

    /** Case 10. Mutation: drop the single-current-manager check. */
    public function test_a_user_has_exactly_one_current_manager(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);

        $subject = $this->make->user($organisation);
        $first = $this->make->user($organisation);
        $second = $this->make->user($organisation);

        $this->management->setManager($subject, $first, $admin);

        // Same day: a correction, so the existing link is amended rather than a
        // zero-length one written beside it.
        $this->management->setManager($subject, $second, $admin);

        $this->assertCount(1, $this->currentManagers($subject->id), 'A second current manager exists for one user.');
        $this->assertSame($second->id, $this->currentManagers($subject->id)->first()->manager_id);
        $this->assertSame(1, ManagementRelationship::query()->where('user_id', $subject->id)->count());

        // A later day is a real change: the previous link is ended, not deleted,
        // so the history stays answerable for P1-07.
        $this->travel(1)->days();
        $this->management->setManager($subject, $first, $admin);

        $this->assertCount(1, $this->currentManagers($subject->id), 'A second current manager exists for one user.');
        $this->assertSame($first->id, $this->currentManagers($subject->id)->first()->manager_id);
        $this->assertSame(2, ManagementRelationship::query()->where('user_id', $subject->id)->count());

        $ended = ManagementRelationship::query()
            ->where('user_id', $subject->id)
            ->whereNotNull('effective_to')
            ->sole();

        $this->assertSame($second->id, $ended->manager_id, 'The superseded link was deleted rather than ended.');
    }

    /** Case 15. Mutation: drop the uniqueness check. */
    public function test_a_user_cannot_hold_two_current_memberships_of_one_team(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        $member = $this->make->user($organisation);

        $this->memberships->add($team, $member, $admin);

        $this->expectViolation('duplicate_membership', fn () => $this->memberships->add($team, $member, $admin));
    }

    /** Removal retains the row so "who was in this team in March" stays answerable. */
    public function test_removing_a_member_retains_the_row(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $team = $this->make->team($this->make->department($this->make->businessUnit($organisation)));
        $member = $this->make->user($organisation);

        $membership = $this->memberships->add($team, $member, $admin);
        $this->memberships->remove($membership, $admin);

        $this->assertSame(1, TeamMembership::query()->where('team_id', $team->id)->count());
        $this->assertNotNull($membership->fresh()->left_at);

        // Rejoining the same day means the removal was a mistake, so the row is
        // reopened rather than a zero-length membership written beside it.
        $this->memberships->add($team, $member, $admin);

        $this->assertSame(1, TeamMembership::query()->where('team_id', $team->id)->count());
        $this->assertNull($membership->fresh()->left_at);

        // Leaving and rejoining on a later day is a real second membership, and
        // the first one is still there to answer for the period it covered.
        $this->memberships->remove($membership->fresh(), $admin);
        $this->travel(1)->days();
        $this->memberships->add($team, $member, $admin);

        $this->assertSame(2, TeamMembership::query()->where('team_id', $team->id)->count());
        $this->assertNotNull($membership->fresh()->left_at);
    }

    /**
     * Case 17. Mutation: render the exception message.
     *
     * A refusal reaching a browser carries the administrator-facing message and
     * the stable reason - never a trace, a framework internal or a SQL fragment.
     */
    public function test_a_refusal_body_carries_no_trace_or_framework_internals(): void
    {
        $organisation = $this->make->organisation();
        $admin = $this->make->user($organisation, administrator: true);
        $unit = $this->make->businessUnit($organisation);
        $this->make->department($unit, 'Engineering');

        $response = $this->actingAsUser($admin)
            ->from('/console/organisation/business-units')
            ->patch("/console/organisation/business-units/{$unit->id}/deactivate");

        $response->assertRedirect('/console/organisation/business-units');

        $body = $this->actingAsUser($admin)->get('/console/organisation/business-units')->getContent();

        foreach (['Stack trace', '#0 /', 'vendor/laravel', 'SQLSTATE', 'Illuminate\\\\Database'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "A refusal body leaked [{$leak}].");
        }
    }

    /**
     * PHP source with every comment and docblock removed, so a guard that scans
     * for a forbidden identifier judges the code and not the explanation of why
     * the code does not use it.
     */
    private function sourceWithoutComments(string $path): string
    {
        $code = '';

        foreach (token_get_all(file_get_contents($path)) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $code .= is_array($token) ? $token[1] : $token;
        }

        return $code;
    }

    /** @return Collection<int, ManagementRelationship> */
    private function currentManagers(int $userId): Collection
    {
        return ManagementRelationship::query()
            ->where('user_id', $userId)
            ->whereNull('effective_to')
            ->get();
    }

    private function expectViolation(string $reason, callable $action): void
    {
        try {
            $action();
            $this->fail("Expected a StructureViolation with reason [{$reason}], but the action was permitted.");
        } catch (StructureViolation $violation) {
            $this->assertSame(
                $reason,
                $violation->reason,
                "The action was refused, but for [{$violation->reason}] rather than [{$reason}] - so this "
                .'test would pass even with the guard it is meant to prove removed.'
            );
        }
    }

    private function actingAsUser(User $user): self
    {
        return $this->withSession([
            EnsureSessionIsCurrent::SESSION_USER_ID => $user->id,
            EnsureSessionIsCurrent::SESSION_AUTHENTICATED_AT => now()->toIso8601String(),
        ]);
    }
}
