<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementCeiling;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Platform\Models\User;

/**
 * Access state for the P1-05 tests, built through the MODELS rather than the
 * services - so a test can construct a state the services would refuse and then
 * prove the engine still fails closed against it.
 *
 * That is deliberate and is what makes several of the negative cases possible
 * at all: an entitlement with no ceiling, a scope with a target where none
 * applies, an ended row that must not participate. The services refuse to
 * create those; the engine must still deny when it meets one.
 */
final class AccessFactory
{
    public function assignment(
        User $user,
        RoleCode $role = RoleCode::BusinessUser,
        ?Organisation $organisation = null,
        ?string $endedAt = null,
    ): RoleAssignment {
        return RoleAssignment::query()->create([
            'user_id' => $user->id,
            'organisation_id' => $role->isPlatformScoped()
                ? null
                : ($organisation?->id ?? $user->organisation_id),
            'role_code' => $role,
            'assigned_at' => now(),
            'ended_at' => $endedAt,
        ]);
    }

    public function entitlement(
        RoleAssignment $assignment,
        BusinessDomain $domain,
        ?string $endedAt = null,
    ): DomainEntitlement {
        return DomainEntitlement::query()->create([
            'role_assignment_id' => $assignment->id,
            'business_domain_id' => $domain->id,
            'granted_at' => now(),
            'ended_at' => $endedAt,
        ]);
    }

    public function scope(
        DomainEntitlement $entitlement,
        ScopeType $type = ScopeType::Organisation,
        ?int $targetId = null,
        ?string $endedAt = null,
    ): EntitlementScope {
        return EntitlementScope::query()->create([
            'domain_entitlement_id' => $entitlement->id,
            'scope_type' => $type,
            'team_id' => $type === ScopeType::Team ? $targetId : null,
            'business_unit_id' => $type === ScopeType::BusinessUnit ? $targetId : null,
            'assigned_at' => now(),
            'ended_at' => $endedAt,
        ]);
    }

    public function ceiling(
        DomainEntitlement $entitlement,
        Sensitivity $sensitivity = Sensitivity::Standard,
        ?string $endedAt = null,
    ): EntitlementCeiling {
        return EntitlementCeiling::query()->create([
            'domain_entitlement_id' => $entitlement->id,
            'sensitivity' => $sensitivity,
            'assigned_at' => now(),
            'ended_at' => $endedAt,
        ]);
    }

    /**
     * A COMPLETE grant path, in one call. The default for tests whose subject
     * is something else and which just need somebody who can see something.
     */
    public function completePath(
        User $user,
        BusinessDomain $domain,
        RoleCode $role = RoleCode::BusinessUser,
        ScopeType $scope = ScopeType::Organisation,
        ?int $targetId = null,
        Sensitivity $ceiling = Sensitivity::Standard,
    ): DomainEntitlement {
        $assignment = $this->assignment($user, $role);
        $entitlement = $this->entitlement($assignment, $domain);
        $this->scope($entitlement, $scope, $targetId);
        $this->ceiling($entitlement, $ceiling);

        return $entitlement;
    }

    public function domain(
        Organisation $organisation,
        string $code = 'finance',
        string $name = 'Finance',
        string $status = 'enabled',
    ): BusinessDomain {
        $domain = new BusinessDomain;

        $domain->forceFill([
            'organisation_id' => $organisation->id,
            'code' => $code,
            'name' => $name,
            'kind' => 'baseline',
            'status' => $status,
            'access_expectation' => 'undecided',
        ])->save();

        return $domain;
    }
}
