<?php

declare(strict_types=1);

namespace App\Modules\Access\Engine;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\DecisionReason;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Security\SecurityEventLogger;
use Throwable;

/**
 * THE ONE EFFECTIVE-ACCESS ENGINE. Every enforcement point and the simulator
 * call this. There is no second implementation, no simulator copy, no cached
 * answer and no authorization computed in JavaScript.
 *
 * decide() and explain() are two entry points over ONE internal evaluator.
 * Evidence mode is not a property of the question - see AccessQuestion - so it
 * is structurally incapable of changing the answer.
 *
 * FAIL CLOSED, EVERYWHERE. Every path through this class returns a decision. An
 * exception, a timeout or an unreachable dependency produces
 * denied_engine_failure and an operational signal - never an error some later
 * change quietly turns into a pass.
 *
 * THE EVALUATION IS A NARROWING. Global gates, then candidate assignments
 * carrying the action class, then their current entitlements for this domain,
 * then domain enabled, then scope, then ceiling. Every step narrows; no step
 * may widen what an earlier one allowed.
 *
 * NO PERMISSION CACHE - D-69. Phase 1 has no data volume to justify one, and a
 * cache is the mechanism by which "immediate" quietly becomes "eventually". A
 * revocation lands on the NEXT decision.
 */
final class AccessEngine
{
    public function __construct(private readonly SecurityEventLogger $events) {}

    /**
     * Enforcement. Short-circuits on the first complete authorising path.
     */
    public function decide(AccessQuestion $question): AccessDecision
    {
        try {
            return $this->evaluate($question, stopAtFirstAllow: true)->toDecision();
        } catch (Throwable $failure) {
            return AccessDecision::deny($this->engineFailed($failure));
        }
    }

    /**
     * The simulator. Same evaluator, same answer, every authorising path.
     */
    public function explain(AccessQuestion $question): AccessExplanation
    {
        try {
            return $this->evaluate($question, stopAtFirstAllow: false);
        } catch (Throwable $failure) {
            return new AccessExplanation(
                false,
                $this->engineFailed($failure),
                null,
                [],
                [],
            );
        }
    }

    /**
     * The single narrow question that replaced nine call sites reading
     * users.platform_role. There is exactly ONE definition of "is this person a
     * System Administrator" in the codebase, and N-B8 breaks it by adding a
     * second.
     *
     * It asks only whether the role is CURRENTLY held. Whether the account is
     * active is a separate question, asked separately by the caller that needs
     * it - the two are never collapsed.
     */
    public function holdsRole(User $user, RoleCode $role, ?int $organisationId = null): bool
    {
        $query = RoleAssignment::query()
            ->where('user_id', $user->getKey())
            ->where('role_code', $role->value)
            ->whereNull('ended_at');

        // A platform-scoped role is held across the platform, so an
        // organisation filter would be a category error rather than a
        // tightening.
        if ($organisationId !== null && ! $role->isPlatformScoped()) {
            $query->where('organisation_id', $organisationId);
        }

        return $query->exists();
    }

    /**
     * THE EVALUATOR. Both entry points reach the answer through this, and
     * nothing else decides anything.
     */
    private function evaluate(AccessQuestion $question, bool $stopAtFirstAllow): AccessExplanation
    {
        $gate = $this->globalGates($question);

        if ($gate !== null) {
            return $this->denied($gate, []);
        }

        $user = $question->user;

        // Guaranteed non-null: the unauthenticated gate above returns first.
        assert($user instanceof User);

        $candidateRoles = RoleCatalogue::rolesPermitting($question->actionClass);

        $assignments = RoleAssignment::query()
            ->where('user_id', $user->getKey())
            ->whereNull('ended_at')
            ->whereIn('role_code', array_map(
                static fn (RoleCode $role): string => $role->value,
                $candidateRoles,
            ))
            ->orderBy('id')
            ->get();

        if ($assignments->isEmpty()) {
            return $this->denied(DecisionReason::DeniedNoRole, []);
        }

        // The four administration classes stop here. They never reach
        // grant-path evaluation, which is what makes "administration authority
        // never implies business-data authority" structural rather than a rule
        // somebody has to remember.
        if (! $question->actionClass->requiresGrantPath()) {
            return new AccessExplanation(true, DecisionReason::AllowedByPath, null, [], []);
        }

        return $this->evaluateBusinessData($question, $user, $assignments->all(), $stopAtFirstAllow);
    }

    /**
     * The global gates. Checked FIRST. No path can satisfy them and no path can
     * outvote them.
     */
    private function globalGates(AccessQuestion $question): ?DecisionReason
    {
        $user = $question->user;

        if (! $user instanceof User) {
            return DecisionReason::DeniedUnauthenticated;
        }

        // An inactive user is a DENIED REQUEST, not merely an absent row.
        // Filtering listings only would leave every direct route open.
        if (! $user->isActive()) {
            return DecisionReason::DeniedInactiveUser;
        }

        if ($question->organisationId !== null
            && $user->organisation_id !== null
            && $user->organisation_id !== $question->organisationId) {
            return DecisionReason::DeniedOrganisationMismatch;
        }

        if (! $question->actionClass->requiresGrantPath()) {
            return null;
        }

        if ($question->businessDomainId === null || $question->resource === null) {
            // A business-data question with no domain or no resource cannot be
            // answered. It is malformed, and a malformed question denies rather
            // than falling through to whatever the rest of the code would do.
            return DecisionReason::DeniedUnknownState;
        }

        $domain = BusinessDomain::query()->find($question->businessDomainId);

        if ($domain === null) {
            return DecisionReason::DeniedUnknownState;
        }

        // THE P1-04 CARRIED GATE. A disabled domain grants nothing, and having
        // NO enabled domains grants nothing rather than everything. There is no
        // empty-set branch anywhere in this method, because the gate is asked
        // about THIS domain rather than about the set.
        if ($domain->status !== DomainStatus::Enabled) {
            return DecisionReason::DeniedDomainDisabled;
        }

        return null;
    }

    /**
     * Independent, complete grant paths - D-62.
     *
     * A path is evaluated WHOLE. It authorises or it does not; it never
     * contributes half an answer to another path. A restrictive grant never
     * reduces what another valid path gave, and a revoked row is NOT a deny -
     * revoked rows are not evaluated at all, so a revocation ends a path
     * without creating a rule.
     *
     * @param  list<RoleAssignment>  $assignments
     */
    private function evaluateBusinessData(
        AccessQuestion $question,
        User $user,
        array $assignments,
        bool $stopAtFirstAllow,
    ): AccessExplanation {
        $authorising = [];
        $failures = [];
        $sawEntitlement = false;
        $sawScopeMatch = false;
        $sawMissingCeiling = false;

        foreach ($assignments as $assignment) {
            $entitlements = DomainEntitlement::query()
                ->where('role_assignment_id', $assignment->getKey())
                ->where('business_domain_id', $question->businessDomainId)
                ->whereNull('ended_at')
                ->orderBy('id')
                ->get();

            foreach ($entitlements as $entitlement) {
                $sawEntitlement = true;

                $scopes = EntitlementScope::query()
                    ->where('domain_entitlement_id', $entitlement->getKey())
                    ->whereNull('ended_at')
                    ->orderBy('id')
                    ->get();

                if ($scopes->isEmpty()) {
                    // The incomplete grant path. The entitlement is current and
                    // authorises nothing until somebody assigns a scope. There
                    // is deliberately NO fallback to domain or organisation:
                    // `scope ?? Scope::Organisation` is the widest possible
                    // mistake, and N-D8 and N-C3 both break it.
                    $failures[] = [
                        'reason' => DecisionReason::DeniedScope,
                        'detail' => 'This entitlement has no active scope.',
                    ];

                    continue;
                }

                $ceiling = EntitlementCeiling::query()
                    ->where('domain_entitlement_id', $entitlement->getKey())
                    ->whereNull('ended_at')
                    ->orderBy('id')
                    ->first();

                foreach ($scopes as $scope) {
                    if (! $this->scopeCovers($scope, $question, $user)) {
                        continue;
                    }

                    // SEVERAL CURRENT SCOPES UNION. Reaching here on ANY of
                    // them is enough; one scope never reduces or cancels
                    // another, and a broader row beside a narrower one makes
                    // the narrower redundant rather than restrictive.
                    $sawScopeMatch = true;

                    if ($ceiling === null) {
                        // Absent, not cleared. A cleared ceiling is a CURRENT
                        // `standard` row; absence is a malformed state and
                        // denies rather than being assumed away.
                        $sawMissingCeiling = true;
                        $failures[] = [
                            'reason' => DecisionReason::DeniedCeilingMissing,
                            'detail' => 'This entitlement has no sensitivity level assigned.',
                        ];

                        continue;
                    }

                    if (! $ceiling->sensitivity->permits($question->sensitivity)) {
                        // DENY, not redact - D-63. A report that quietly drops
                        // a column is one whose reader believes they are seeing
                        // everything.
                        $failures[] = [
                            'reason' => DecisionReason::DeniedCeiling,
                            'detail' => 'This entitlement is limited to '
                                .mb_strtolower($ceiling->sensitivity->label()).' information.',
                        ];

                        continue;
                    }

                    $authorising[] = new GrantPathReference(
                        $assignment->getKey(),
                        $assignment->role_code,
                        $entitlement->getKey(),
                        $entitlement->business_domain_id,
                        $scope->getKey(),
                        $scope->scope_type,
                        $scope->effectiveTarget(),
                        $ceiling->getKey(),
                        $ceiling->sensitivity,
                    );

                    if ($stopAtFirstAllow) {
                        // Enforcement needs one path, and stopping here is what
                        // makes it cheap. explain() does not stop, because the
                        // simulator's answer would otherwise mislead.
                        break 3;
                    }
                }
            }
        }

        if ($authorising !== []) {
            usort(
                $authorising,
                static fn (GrantPathReference $a, GrantPathReference $b): int => $a->order() <=> $b->order(),
            );

            return new AccessExplanation(
                true,
                DecisionReason::AllowedByPath,
                $authorising[0],
                $authorising,
                $failures,
            );
        }

        // WHICH LINK was missing in EVERY candidate path. Reported at the
        // furthest point reached, so the administrator is told the thing they
        // can act on rather than the first thing that failed.
        $reason = match (true) {
            ! $sawEntitlement => DecisionReason::DeniedNoEntitlement,
            ! $sawScopeMatch => DecisionReason::DeniedScope,
            $sawMissingCeiling => DecisionReason::DeniedCeilingMissing,
            default => DecisionReason::DeniedCeiling,
        };

        return $this->denied($reason, $failures);
    }

    /**
     * Does this one scope row cover the requested record?
     *
     * NO INFERENCE AND NO RECURSION - D-66, SYS-005. management_relationships
     * and team_memberships are read only to answer "is this record within the
     * team named on this scope?", never "which teams should this manager have?".
     * Deriving a team from a direct report would give a manager a team nobody
     * assigned, appearing in no grant and no review; walking the chain would
     * grow a senior manager's scope every time somebody is hired three levels
     * below them, with no assignment change to review.
     *
     * Deeper reach is available and deliberate: assign the team.
     */
    private function scopeCovers(EntitlementScope $scope, AccessQuestion $question, User $user): bool
    {
        $resource = $question->resource;

        if ($resource === null) {
            return false;
        }

        return match ($scope->scope_type) {
            // D-67. Phase 2 supplies the per-data-product mapping and
            // implements this contract rather than reinterpreting it.
            ScopeType::Own => $resource->belongsTo($user->getKey()),

            ScopeType::Team => $scope->team_id !== null
                && $resource->isInTeam($scope->team_id),

            ScopeType::BusinessUnit => $scope->business_unit_id !== null
                && $resource->isInBusinessUnit($scope->business_unit_id),

            // D-74. Both reach every record in the ENTITLED domain, through
            // this one resolver. The domain itself was fixed by the entitlement
            // long before this line, so neither can widen it - scope selects
            // records WITHIN the entitled domain and never reaches outside it.
            ScopeType::Domain, ScopeType::Organisation => true,
        };
    }

    /** @param list<array{reason: DecisionReason, detail: string}> $failures */
    private function denied(DecisionReason $reason, array $failures): AccessExplanation
    {
        if ($reason->isSecurityEvent()) {
            // D-71: privileged surfaces and malformed state only. Routine
            // business denials are NOT logged - volume buries what matters and
            // P1-08 would inherit the noise.
            $this->events->record(SecurityEventLogger::ACCESS_STATE_UNRECOGNISED, [
                'result' => 'denied',
                'reason' => $reason->value,
            ]);
        }

        return new AccessExplanation(false, $reason, null, [], $failures);
    }

    /**
     * A failing engine must not look like an ordinary lack of entitlement, so
     * this raises an operational signal as well as denying. Without it a broken
     * deployment would look like one that was working correctly.
     */
    private function engineFailed(Throwable $failure): DecisionReason
    {
        report($failure);

        $this->events->record(SecurityEventLogger::ACCESS_ENGINE_FAILED, [
            'result' => 'denied',
            'reason' => DecisionReason::DeniedEngineFailure->value,
        ]);

        return DecisionReason::DeniedEngineFailure;
    }
}
