<?php

declare(strict_types=1);

namespace Tests\Feature\Access;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Engine\GrantPathReference;
use App\Modules\Access\Engine\ResourceReference;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AccessFactory;
use Tests\Support\OrganisationFactory;
use Tests\TestCase;

/**
 * D-62. INDEPENDENT, COMPLETE GRANT PATHS.
 *
 * A path is evaluated whole. It authorises or it does not, and it never
 * contributes half an answer to another path.
 */
final class GrantPathIndependenceTest extends TestCase
{
    use RefreshDatabase;

    private OrganisationFactory $make;

    private AccessFactory $access;

    private AccessEngine $engine;

    private Organisation $organisation;

    private BusinessDomain $finance;

    private User $person;

    private Team $north;

    protected function setUp(): void
    {
        parent::setUp();

        $this->make = new OrganisationFactory;
        $this->access = new AccessFactory;
        $this->engine = app(AccessEngine::class);

        $this->organisation = $this->make->organisation();
        $this->finance = $this->access->domain($this->organisation);

        $unit = $this->make->businessUnit($this->organisation);
        $this->north = $this->make->team($this->make->department($unit), 'Team North');

        $this->person = $this->make->user($this->organisation);
    }

    /**
     * N-P1. AN INCOMPLETE PATH CONTRIBUTES NOTHING.
     *
     * A path missing its scope does not lend its role to a path missing its
     * role. Two halves of two different grants are not a grant.
     */
    public function test_two_incomplete_paths_do_not_combine_into_one(): void
    {
        // Path A: role and entitlement, NO scope.
        $a = $this->access->assignment($this->person, RoleCode::Manager, $this->organisation);
        $entitlementA = $this->access->entitlement($a, $this->finance);
        $this->access->ceiling($entitlementA, Sensitivity::Standard);

        // Path B: role and entitlement and scope, NO ceiling.
        $b = $this->access->assignment($this->person, RoleCode::Executive, $this->organisation);
        $entitlementB = $this->access->entitlement($b, $this->finance);
        $this->access->scope($entitlementB, ScopeType::Organisation);

        $this->assertFalse(
            $this->engine->decide($this->question())->allowed,
            'Two incomplete paths combined into one. A path is evaluated whole.'
        );
    }

    /**
     * N-P2 and N-P4. A CEILING CAPS ITS OWN PATH ONLY, and a restrictive grant
     * never reduces another.
     *
     * Path A is capped at Standard; path B allows Confidential. A Confidential
     * request is ALLOWED, through B.
     *
     * Mutation: make the lowest ceiling a global maximum; intersect paths
     * instead of evaluating them independently.
     */
    public function test_a_low_ceiling_on_one_path_does_not_cap_another(): void
    {
        $a = $this->access->assignment($this->person, RoleCode::BusinessUser, $this->organisation);
        $entitlementA = $this->access->entitlement($a, $this->finance);
        $this->access->scope($entitlementA, ScopeType::Organisation);
        $this->access->ceiling($entitlementA, Sensitivity::Standard);

        $b = $this->access->assignment($this->person, RoleCode::Executive, $this->organisation);
        $entitlementB = $this->access->entitlement($b, $this->finance);
        $this->access->scope($entitlementB, ScopeType::Organisation);
        $this->access->ceiling($entitlementB, Sensitivity::Confidential);

        $this->assertTrue(
            $this->engine->decide($this->question(Sensitivity::Confidential))->allowed,
            'A Standard ceiling on one path capped a Confidential path. A ceiling caps its own '
            .'path only, never globally.'
        );

        // And Restricted is still refused, so the assertion above is not simply
        // "everything is allowed".
        $this->assertFalse($this->engine->decide($this->question(Sensitivity::Restricted))->allowed);
    }

    /**
     * N-P3. A REVOKED ROW IS NOT A DENY.
     *
     * D-64 rejects explicit deny records. If a revoked row could subtract from
     * a live grant it would BECOME one - invisible, created by an ordinary
     * revocation, chosen by nobody and shown on no screen.
     *
     * Mutation: let a revoked row veto an active path.
     */
    public function test_a_revoked_grant_does_not_veto_an_active_one(): void
    {
        $revoked = $this->access->assignment(
            $this->person,
            RoleCode::Manager,
            $this->organisation,
            endedAt: now()->toDateTimeString(),
        );
        $revokedEntitlement = $this->access->entitlement($revoked, $this->finance, endedAt: now()->toDateTimeString());
        $this->access->scope($revokedEntitlement, ScopeType::Team, $this->north->id, endedAt: now()->toDateTimeString());
        $this->access->ceiling($revokedEntitlement, Sensitivity::Standard, endedAt: now()->toDateTimeString());

        $this->access->completePath($this->person, $this->finance, RoleCode::Executive, ScopeType::Organisation);

        $this->assertTrue(
            $this->engine->decide($this->question())->allowed,
            'A revoked grant vetoed an active one. A revocation ends a path; it does not create a rule.'
        );

        /*
         * AND THE REVOKED ROWS ARE NOT EVALUATED AT ALL.
         *
         * Observed from the emitted SQL, because "the answer is still allow" is
         * satisfied by an engine that reads revoked rows and happens to find
         * them incomplete. What must be true is stronger: they never enter the
         * evaluation, so they can never subtract from it.
         *
         * A mutation dropping the ended_at filter from the assignment query
         * first SURVIVED against the assertion above alone - the revoked path's
         * children were ended too, so no path completed and the answer was
         * unchanged.
         */
        $statements = [];

        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        $this->engine->decide($this->question());

        $selects = array_values(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'select')
                && str_contains($sql, 'role_assignments')
        ));

        $this->assertNotEmpty($selects, 'The engine read no assignments, so this proves nothing.');

        foreach ($selects as $sql) {
            /*
             * QUOTE-AGNOSTIC. SQLite writes "ended_at"; MySQL writes
             * `ended_at`. The first version asserted the SQLite spelling and
             * passed locally while FAILING on the engine production uses -
             * which is the whole reason the Access suite runs against MySQL in
             * CI, and it caught this on the first run.
             */
            $this->assertMatchesRegularExpression(
                '/[`"]ended_at[`"] is null/',
                $sql,
                'The engine read role assignments without filtering to current ones, so a revoked '
                .'row enters the evaluation and can subtract from it.'
            );
        }
    }

    /**
     * N-EN1. THE SAME QUESTION YIELDS THE SAME ANSWER IN BOTH EVIDENCE MODES.
     *
     * Run across a matrix of states rather than one, because the drift this
     * catches would appear at one branch and not another.
     *
     * Mutation: let explain() evaluate differently - the drift that gives a
     * confident wrong answer exactly when somebody needs the truth.
     */
    public function test_decide_and_explain_always_agree(): void
    {
        $scenarios = [
            'nothing granted' => fn () => null,
            'complete path' => fn () => $this->access->completePath($this->person, $this->finance),
            'no scope' => function () {
                $assignment = $this->access->assignment($this->person, RoleCode::BusinessUser, $this->organisation);
                $entitlement = $this->access->entitlement($assignment, $this->finance);
                $this->access->ceiling($entitlement, Sensitivity::Standard);
            },
            'no ceiling' => function () {
                $assignment = $this->access->assignment($this->person, RoleCode::BusinessUser, $this->organisation);
                $entitlement = $this->access->entitlement($assignment, $this->finance);
                $this->access->scope($entitlement, ScopeType::Organisation);
            },
        ];

        foreach ($scenarios as $label => $build) {
            // Child first. The foreign keys are RESTRICT, which is the point:
            // the schema refuses to orphan a scope, so the teardown has to
            // respect the same parentage the services do.
            EntitlementScope::query()->delete();
            EntitlementCeiling::query()->delete();
            DomainEntitlement::query()->delete();
            RoleAssignment::query()->delete();

            $build();

            foreach ([Sensitivity::Standard, Sensitivity::Confidential, Sensitivity::Restricted] as $level) {
                $question = $this->question($level);

                $decision = $this->engine->decide($question);
                $explanation = $this->engine->explain($question);

                $this->assertSame(
                    $decision->allowed,
                    $explanation->allowed,
                    "[{$label}/{$level->value}] decide() and explain() disagreed on allow/deny."
                );

                $this->assertSame(
                    $decision->reason,
                    $explanation->reason,
                    "[{$label}/{$level->value}] decide() and explain() gave different primary reasons."
                );
            }
        }
    }

    /**
     * explain() returns EVERY authorising path; decide() returns one.
     *
     * This is the question an administrator asks before revoking, and a
     * first-match answer would tell them access comes from one grant when
     * another also allows it.
     */
    public function test_explain_returns_every_authorising_path_and_decide_returns_one(): void
    {
        $this->access->completePath($this->person, $this->finance, RoleCode::Manager, ScopeType::Team, $this->north->id);
        $this->access->completePath($this->person, $this->finance, RoleCode::Executive, ScopeType::Organisation);

        $question = $this->question();

        $explanation = $this->engine->explain($question);

        $this->assertTrue($explanation->allowed);
        $this->assertCount(
            2,
            $explanation->authorisingPaths,
            'explain() did not return every authorising path, so the simulator would mislead an '
            .'administrator about what revoking one grant would do.'
        );

        // Removing either one still leaves access.
        foreach ($explanation->authorisingPaths as $path) {
            $this->assertTrue(
                $explanation->remainsAllowedWithout($path->roleAssignmentId),
                'Removing one of two authorising paths was reported as removing access.'
            );
        }

        $this->assertNotNull($this->engine->decide($question)->authorisingPath);
    }

    /**
     * N-EN2. THE PRIMARY PATH IS DETERMINISTIC - assignment, entitlement,
     * scope, ceiling, all ascending.
     *
     * Mutation: remove the ordering and take whatever the database returned.
     * It passes every allow/deny assertion and surfaces only as flakiness - so
     * this asserts the ORDER rather than merely that a path came back.
     */
    public function test_the_primary_authorising_path_is_deterministically_ordered(): void
    {
        $this->access->completePath($this->person, $this->finance, RoleCode::Executive, ScopeType::Organisation);
        $this->access->completePath($this->person, $this->finance, RoleCode::Manager, ScopeType::Team, $this->north->id);
        $this->access->completePath($this->person, $this->finance, RoleCode::BusinessUser, ScopeType::Organisation);

        $explanation = $this->engine->explain($this->question());

        $orders = array_map(
            static fn (GrantPathReference $path): array => $path->order(),
            $explanation->authorisingPaths,
        );

        $sorted = $orders;
        usort($sorted, static fn (array $a, array $b): int => $a <=> $b);

        $this->assertSame($sorted, $orders, 'The authorising paths were not deterministically ordered.');

        $this->assertSame(
            $orders[0],
            $explanation->primaryPath->order(),
            'The primary path is not the first in the deterministic order.'
        );
    }

    /**
     * N-B18 and D-69. REVOCATION LANDS ON THE NEXT DECISION.
     *
     * "Immediate" means the next decision after the change commits. There is
     * no permission cache, and no session expiry standing in for one.
     *
     * Mutation: cache the decision.
     */
    public function test_a_revocation_lands_on_the_very_next_decision(): void
    {
        $entitlement = $this->access->completePath($this->person, $this->finance);

        $this->assertTrue($this->engine->decide($this->question())->allowed);

        $entitlement->forceFill(['ended_at' => now()])->save();

        $decision = $this->engine->decide($this->question());

        $this->assertFalse(
            $decision->allowed,
            'A revocation did not land on the next decision. A cache is the mechanism by which '
            .'"immediate" quietly becomes "eventually".'
        );
        $this->assertSame(DecisionReason::DeniedNoEntitlement, $decision->reason);
    }

    private function question(Sensitivity $sensitivity = Sensitivity::Standard): AccessQuestion
    {
        return AccessQuestion::businessData(
            $this->person,
            'read',
            $this->finance->id,
            new ResourceReference(
                subjectUserId: $this->person->id,
                teamIds: [$this->north->id],
                organisationId: $this->organisation->id,
            ),
            $sensitivity,
        );
    }
}
