<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Department;
use App\Modules\Organisation\Models\LegalEntity;
use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Models\StructureStatus;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use App\Modules\Platform\Models\UserStatus;

/**
 * Structure for the P1-01 tests, built through the models rather than the
 * services, so a test can construct a state the services would refuse and then
 * assert that they refuse to act on it.
 */
final class OrganisationFactory
{
    public function organisation(string $name = 'Acme'): Organisation
    {
        return Organisation::query()->create(['name' => $name, 'status' => StructureStatus::Active]);
    }

    public function legalEntity(Organisation $organisation, string $name = 'Acme Pte Ltd'): LegalEntity
    {
        return LegalEntity::query()->create([
            'organisation_id' => $organisation->id,
            'name' => $name,
            'status' => StructureStatus::Active,
        ]);
    }

    public function businessUnit(Organisation $organisation, string $name = 'Delivery'): BusinessUnit
    {
        return BusinessUnit::query()->create([
            'organisation_id' => $organisation->id,
            'name' => $name,
            'status' => StructureStatus::Active,
        ]);
    }

    public function department(BusinessUnit $unit, string $name = 'Engineering'): Department
    {
        return Department::query()->create([
            'organisation_id' => $unit->organisation_id,
            'business_unit_id' => $unit->id,
            'name' => $name,
            'status' => StructureStatus::Active,
        ]);
    }

    public function team(Department $department, string $name = 'Platform'): Team
    {
        return Team::query()->create([
            'organisation_id' => $department->organisation_id,
            'department_id' => $department->id,
            'name' => $name,
            'status' => StructureStatus::Active,
        ]);
    }

    /**
     * A user, optionally associated with an organisation.
     *
     * tenant_id is set to a fixed directory value INDEPENDENT of
     * organisation_id, so that a guard which reads tenant_id instead of
     * organisation_id gives a different - and wrong - answer. That separation is
     * what makes negative test 19 non-vacuous.
     *
     * `administrator: true` now creates a ROLE ASSIGNMENT rather than setting a
     * column, because D-49 removed the column. The assignment is
     * platform-scoped - organisation_id NULL - exactly as bootstrap creates it,
     * so a fixture administrator is the same shape as a real one. A fixture
     * that took a shortcut here would be a fixture more helpful than reality,
     * which is the failure CLAUDE.md §2 names.
     */
    public function user(
        ?Organisation $organisation = null,
        bool $administrator = false,
        string $tenant = '11111111-1111-1111-1111-111111111111',
        UserStatus $status = UserStatus::Active,
    ): User {
        static $sequence = 0;
        $sequence++;

        $user = User::query()->create([
            'organisation_id' => $organisation?->id,
            'provider' => 'microsoft',
            'external_subject' => "subject-{$sequence}",
            'tenant_id' => $tenant,
            'email' => "user{$sequence}@example.test",
            'display_name' => "User {$sequence}",
            'status' => $status,
        ]);

        if ($administrator) {
            RoleAssignment::query()->create([
                'user_id' => $user->id,
                'organisation_id' => null,
                'role_code' => RoleCode::SystemAdministrator,
                'assigned_at' => now(),
                'ended_at' => null,
                'assigned_by_user_id' => null,
            ]);
        }

        return $user;
    }

    /**
     * A role assignment, for tests that need a role other than System
     * Administrator.
     */
    public function roleAssignment(
        User $user,
        RoleCode $role,
        ?Organisation $organisation = null,
    ): RoleAssignment {
        return RoleAssignment::query()->create([
            'user_id' => $user->id,
            'organisation_id' => $role->isPlatformScoped() ? null : ($organisation?->id ?? $user->organisation_id),
            'role_code' => $role,
            'assigned_at' => now(),
            'ended_at' => null,
            'assigned_by_user_id' => null,
        ]);
    }
}
