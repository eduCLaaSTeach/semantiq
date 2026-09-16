<?php

declare(strict_types=1);

namespace App\Modules\Security\Posture\Adapters;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Platform\Models\UserStatus;
use App\Modules\Security\Catalogue\ControlCatalogue;
use App\Modules\Security\Posture\Evidence;
use App\Modules\Security\Posture\PostureState;

/**
 * Privileged Access Health's access rows - and the line between the two kinds.
 *
 * STATE-BEARING (PR-3, PR-6): an OBSERVED CONDITION that is wrong, or that
 * nobody has verified.
 *
 * COUNT ONLY (PR-2, PR-4, PR-5, PR-8): a LEGITIMATE, APPROVED state worth
 * seeing, which is not by itself a finding. Each returns Evidence::count(),
 * which carries no state, and MetricRow has no field to hold one.
 *
 * WHY THAT LINE IS WHERE IT IS. P1-05 deliberately delivers Restricted
 * sensitivity grants, several Organisation Administrators, whole-domain scope
 * and assignments preserved across deactivation - each protected by its own
 * control and each approved. Painting them amber would mean this screen
 * declaring approved P1-05 behaviour to be a fault, training administrators
 * that amber means nothing, and inventing risk from a legitimate state. P1-07
 * owns whether a PARTICULAR grant is overdue or unreviewed. P1-06 shows it
 * exists. Giving PR-4 a state is the most tempting wrong edit in this unit,
 * because "a Restricted grant should be amber" sounds like caution; N-SS14,
 * N-SS15, N-SS16 and N-SS17 break it from both directions.
 *
 * NOTHING HERE READS access_expectation. D-61 makes it context only and the
 * engine never reads it; a posture screen that did would be a second opinion
 * about access. N-SS18's sibling guard greps for it.
 *
 * NO THRESHOLD IS INVENTED - D-78. PR-2 and PR-8 report a number and attach no
 * judgement, because an invented threshold is invented risk.
 */
final class GrantPathAdapter implements SourceAdapter
{
    public function answers(): array
    {
        return [
            ControlCatalogue::PRIVILEGED_WITH_DATA,
            ControlCatalogue::INCOMPLETE_PATHS,
            ControlCatalogue::ORGANISATION_ADMINISTRATORS,
            ControlCatalogue::RESTRICTED_GRANTS,
            ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS,
            ControlCatalogue::BROAD_SCOPES,
        ];
    }

    public function evidence(): array
    {
        return [
            $this->privilegedHoldingBusinessData(),
            $this->incompleteGrantPaths(),
            $this->organisationAdministrators(),
            $this->restrictedGrants(),
            $this->inactiveWithAssignments(),
            $this->broadScopes(),
        ];
    }

    // ---- Posture controls. STATE-BEARING. ---------------------------------

    /**
     * PR-3 - administration authority that has also become business-data
     * authority. The high-impact COMBINATION this unit exists to surface.
     *
     * Worded as an explicit permitted grant worth reviewing, NEVER as a policy
     * violation: it is permitted, and calling a permitted thing a violation is
     * how a screen loses its reader.
     */
    private function privilegedHoldingBusinessData(): Evidence
    {
        $privileged = array_map(
            static fn (RoleCode $role): string => $role->value,
            RoleCatalogue::requiringStepUp(),
        );

        /*
         * THE ASSIGNMENT MUST BELONG TO SOMEBODY WHO IS STILL ACTIVE.
         *
         * Without the status filter this counted a PRESERVED assignment on a
         * deactivated account and reported "an administrator also holds
         * business access" about somebody who cannot reach anything at all:
         * AccessEngine denies an inactive user at the GLOBAL GATE, before any
         * role, entitlement, scope or ceiling is considered, so no grant of
         * theirs can authorise a single row.
         *
         * That is inventing risk from a legitimate state - exactly the failure
         * the posture-control/metric split exists to prevent, arriving through
         * a posture control instead of a metric. P1-03 preserves relationships
         * deliberately so access can be restored, and PR-5 is where a preserved
         * assignment belongs: a COUNT, with the sentence explaining why it is
         * harmless. PR-10 watches the gate that makes it harmless.
         *
         * "Effective" here means the same thing AdministratorSetGuard means by
         * it - a current assignment held by an active user, BOTH filters - and
         * the status is read through the assignment's own `user` relationship
         * rather than re-implemented. No engine logic is duplicated: this asks
         * who the assignment belongs to, not whether they may see anything.
         *
         * Nothing is ended, revoked or deleted. Reactivating the account makes
         * the same assignment and the same entitlement count again immediately,
         * with nothing re-granted.
         */
        $count = DomainEntitlement::query()
            ->current()
            ->whereHas('assignment', function ($query) use ($privileged): void {
                $query
                    ->whereNull('ended_at')
                    ->whereIn('role_code', $privileged)
                    ->whereHas('user', fn ($user) => $user->where('status', UserStatus::Active->value));
            })
            ->count();

        if ($count === 0) {
            return Evidence::state(
                ControlCatalogue::PRIVILEGED_WITH_DATA,
                PostureState::Healthy,
                'No administrator currently holds an entitlement to business information. '
                .'Administration authority and business information stay separate.',
            );
        }

        return Evidence::state(
            ControlCatalogue::PRIVILEGED_WITH_DATA,
            PostureState::Attention,
            $count === 1
                ? 'One administrator also holds an entitlement to business information. This is '
                    .'permitted and was granted deliberately; it is worth reviewing that it is '
                    .'still needed.'
                : "{$count} administrators also hold entitlements to business information. These "
                    .'are permitted and were granted deliberately; they are worth reviewing.',
        );
    }

    /**
     * PR-6 - a current entitlement with no current scope, or no current ceiling.
     *
     * It authorises nothing today, which is exactly why it persists unnoticed:
     * it is incomplete CONFIGURATION somebody believes is working, not an open
     * door. Attention, because somebody will eventually be surprised by it.
     */
    private function incompleteGrantPaths(): Evidence
    {
        $count = DomainEntitlement::query()
            ->current()
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('scopes', fn ($scope) => $scope->whereNull('ended_at'))
                    ->orWhereDoesntHave('ceilings', fn ($ceiling) => $ceiling->whereNull('ended_at'));
            })
            ->count();

        if ($count === 0) {
            return Evidence::state(
                ControlCatalogue::INCOMPLETE_PATHS,
                PostureState::Healthy,
                'Every entitlement has both a scope and a sensitivity limit, so none of them is '
                .'silently granting nothing.',
            );
        }

        return Evidence::state(
            ControlCatalogue::INCOMPLETE_PATHS,
            PostureState::Attention,
            $count === 1
                ? 'One entitlement is missing a scope or a sensitivity limit. It grants nothing '
                    .'today, so somebody may believe it is working when it is not.'
                : "{$count} entitlements are missing a scope or a sensitivity limit. They grant "
                    .'nothing today, so somebody may believe they are working when they are not.',
        );
    }

    // ---- Informational metrics. COUNT AND CONTEXT, NO STATE. --------------

    /** PR-2. No threshold is invented - D-78. */
    private function organisationAdministrators(): Evidence
    {
        $count = RoleAssignment::query()
            ->current()
            ->where('role_code', RoleCode::OrganisationAdministrator->value)
            ->count();

        return Evidence::count(
            ControlCatalogue::ORGANISATION_ADMINISTRATORS,
            $count,
            'No number is treated as too many or too few. This is a count, not a finding.',
        );
    }

    /**
     * PR-4. Restricted is an APPROVED P1-05 capability protected by step-up.
     * A legitimate grant does not make a deployment amber.
     */
    private function restrictedGrants(): Evidence
    {
        $count = EntitlementCeiling::query()
            ->current()
            ->where('sensitivity', Sensitivity::Restricted->value)
            ->count();

        return Evidence::count(
            ControlCatalogue::RESTRICTED_GRANTS,
            $count,
            'Restricted access is granted deliberately and is confirmed with a fresh Microsoft '
            .'sign-in. Whether a particular grant is still needed is an access review question.',
        );
    }

    /**
     * PR-5. P1-05 BEHAVING AS DESIGNED: P1-03 preserves relationships and the
     * inactive-user gate removes effective access. The real risk is the GATE
     * failing, which is PR-10 and carries a state.
     */
    private function inactiveWithAssignments(): Evidence
    {
        $count = RoleAssignment::query()
            ->current()
            ->whereHas('user', fn ($user) => $user->where('status', UserStatus::Inactive->value))
            ->count();

        return Evidence::count(
            ControlCatalogue::INACTIVE_WITH_ASSIGNMENTS,
            $count,
            'Access is preserved so it can be restored if somebody returns. An account that is '
            .'not active has no effective access while the check above is in place.',
        );
    }

    /** PR-8. A broad scope is a deliberate grant. No threshold - D-78. */
    private function broadScopes(): Evidence
    {
        $broad = array_map(
            static fn (ScopeType $type): string => $type->value,
            array_values(array_filter(
                ScopeType::cases(),
                static fn (ScopeType $type): bool => $type->coversWholeDomain(),
            )),
        );

        $count = EntitlementScope::query()
            ->current()
            ->whereIn('scope_type', $broad)
            ->count();

        return Evidence::count(
            ControlCatalogue::BROAD_SCOPES,
            $count,
            'A grant covering a whole domain is a deliberate choice. No number is treated as too '
            .'many.',
        );
    }
}
