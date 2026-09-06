<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Http\Controllers\Concerns\InteractsWithAccess;
use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\EntitlementScope;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Services\AdministratorSetGuard;
use App\Modules\Access\Services\EntitlementService;
use App\Modules\Access\Services\RoleAssignmentService;
use App\Modules\Access\StepUp\StepUpAction;
use App\Modules\Access\StepUp\StepUpService;
use App\Modules\Access\Support\AccessViolation;
use App\Modules\Access\Support\RoleCatalogue;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Domains\Models\DomainStatus;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Roles & Access: who holds which role, what each role is entitled to, which
 * records that reaches, and how sensitive the information may be.
 *
 * FOUR DIMENSIONS, ONE PAGE EACH LEVEL DOWN. The list shows role assignments;
 * everything beneath one - entitlements, scopes, ceilings - lives on that
 * assignment's record page, because a scope means nothing without the
 * entitlement it hangs from and a screen that separated them would ask the
 * reader to hold the relationship in their head.
 *
 * NOTHING HERE DECIDES ANYTHING. Every authorization answer on these screens
 * comes from AccessEngine, and the controller's job is to present it. There is
 * no second evaluation here and none in the JavaScript.
 */
final class AccessController
{
    use InteractsWithAccess;

    private const PER_PAGE = 25;

    public function __construct(
        private readonly RoleAssignmentService $roles,
        private readonly EntitlementService $entitlements,
        private readonly AdministratorSetGuard $administrators,
        private readonly StepUpService $stepUp,
    ) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $state = (string) $request->query('state', '');

        $assignments = RoleAssignment::query()
            ->where(fn ($query) => $query
                ->where('organisation_id', $organisation->id)
                // Platform-scoped assignments carry no organisation and must
                // still be visible, or the System Administrator would vanish
                // from the screen that manages administrators.
                ->orWhereNull('organisation_id'))
            ->when($search !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($q) => $q->where('display_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->when(RoleCode::tryFrom($role) !== null, fn ($query) => $query->where('role_code', $role))
            ->when($state === 'current', fn ($query) => $query->whereNull('ended_at'))
            ->when($state === 'ended', fn ($query) => $query->whereNotNull('ended_at'))
            // Default to current, because an administrator opening this screen
            // is asking who has access now, not who ever did.
            ->when($state === '', fn ($query) => $query->whereNull('ended_at'))
            ->with(['user:id,display_name,email,status'])
            ->withCount(['entitlements as current_entitlements_count' => fn ($query) => $query->whereNull('ended_at')])
            ->orderBy('role_code')
            ->orderByDesc('assigned_at')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (RoleAssignment $assignment): array => $this->summary($assignment));

        return Inertia::render('Access/Index', [
            'assignments' => $assignments,
            'filters' => ['search' => $search, 'role' => $role, 'state' => $state],
            'roles' => $this->roleOptions(),
            'candidates' => $this->candidates($organisation->id),
            // Two different facts: nothing has been granted at all, versus a
            // filter matched nothing. P1-03 shipped a defect by conflating them.
            'anyAssignments' => RoleAssignment::query()
                ->where(fn ($query) => $query->where('organisation_id', $organisation->id)->orWhereNull('organisation_id'))
                ->exists(),
            // D-49a. Informational, never a block.
            'soleAdministratorWarning' => $this->administrators->isSoleAdministratorWarningActive()
                ? AdministratorSetGuard::SOLE_ADMINISTRATOR_WARNING
                : null,
        ]);
    }

    public function show(Request $request, RoleAssignment $assignment): Response
    {
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        $assignment->load('user:id,display_name,email,status');

        $entitlements = DomainEntitlement::query()
            ->where('role_assignment_id', $assignment->id)
            ->with([
                'domain:id,name,code,status',
                'scopes' => fn ($query) => $query->orderByDesc('assigned_at')->orderByDesc('id'),
                'scopes.team:id,name',
                'scopes.businessUnit:id,name',
                'ceilings' => fn ($query) => $query->orderByDesc('assigned_at')->orderByDesc('id'),
            ])
            ->orderByDesc('granted_at')
            ->orderByDesc('id')
            ->get();

        $organisationId = $assignment->organisation_id ?? $this->organisation($request)->id;

        return Inertia::render('Access/Record', [
            'assignment' => $this->summary($assignment) + [
                'assignedAt' => $assignment->assigned_at->toDateString(),
                'endedAt' => $assignment->ended_at?->toDateString(),
                'summary' => $assignment->role_code->summary(),
                'platformScoped' => $assignment->role_code->isPlatformScoped(),
            ],
            'entitlements' => $entitlements->map(fn (DomainEntitlement $entitlement): array => $this->entitlementSummary($entitlement))->all(),
            'domains' => BusinessDomain::query()
                ->where('organisation_id', $organisationId)
                ->orderBy('name')
                ->get(['id', 'name', 'code', 'status'])
                ->map(fn (BusinessDomain $domain): array => [
                    'id' => $domain->id,
                    'name' => $domain->name,
                    'code' => $domain->code,
                    'enabled' => $domain->status === DomainStatus::Enabled,
                ])->all(),
            'scopeTypes' => $this->scopeOptions(),
            'sensitivities' => $this->sensitivityOptions(),
            'teams' => Team::query()
                ->whereHas('department.businessUnit', fn ($query) => $query->where('organisation_id', $organisationId))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Team $team): array => ['id' => $team->id, 'name' => $team->name])->all(),
            'businessUnits' => BusinessUnit::query()
                ->where('organisation_id', $organisationId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (BusinessUnit $unit): array => ['id' => $unit->id, 'name' => $unit->name])->all(),
        ]);
    }

    /**
     * Grant a role.
     *
     * STEP-UP FIRST, FOR THE PRIVILEGED ONES. The redirect happens before the
     * service is called, so the action does not exist anywhere until a fresh
     * authentication has been proven.
     */
    public function assignRole(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role_code' => ['required', 'string'],
        ]);

        $role = RoleCode::tryFrom($data['role_code']);

        if ($role === null) {
            return $this->refuse(AccessViolation::roleNotGrantable(RoleCode::BusinessUser));
        }

        $subject = User::query()->findOrFail($data['user_id']);
        $actor = $this->actor($request);

        $stepUpAction = $this->stepUpActionForGrant($role, $subject, $actor);

        if ($stepUpAction !== null) {
            return $this->beginStepUp($request, $stepUpAction, [
                'subject_user_id' => $subject->id,
                'role_code' => $role->value,
            ]);
        }

        try {
            $assignment = $this->roles->assign(
                $subject,
                $role,
                $role->isPlatformScoped() ? null : $this->organisation($request)->id,
                $actor,
            );
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('access.show', 'Role granted.', $assignment->id);
    }

    public function revokeRole(Request $request, RoleAssignment $assignment): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        if ($assignment->role_code === RoleCode::SystemAdministrator) {
            return $this->beginStepUp($request, StepUpAction::RevokeSystemAdministrator, [
                'subject_user_id' => $assignment->user_id,
                'role_code' => $assignment->role_code->value,
            ]);
        }

        try {
            $this->roles->revoke($assignment, $this->actor($request));
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('access.show', 'Role revoked. Any entitlements beneath it were revoked with it.', $assignment->id);
    }

    public function grantEntitlement(Request $request, RoleAssignment $assignment): RedirectResponse
    {
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        $data = $request->validate([
            'business_domain_id' => ['required', 'integer', 'exists:business_domains,id'],
        ]);

        $domain = BusinessDomain::query()->findOrFail($data['business_domain_id']);

        try {
            $this->entitlements->grant($assignment, $domain, $this->actor($request));
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm(
            'access.show',
            'Domain entitlement granted. Assign a scope to make it effective.',
            $assignment->id,
        );
    }

    public function revokeEntitlement(Request $request, DomainEntitlement $entitlement): RedirectResponse
    {
        $assignment = $entitlement->assignment;
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        try {
            $this->entitlements->revoke($entitlement, $this->actor($request));
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('access.show', 'Domain entitlement revoked.', $assignment->id);
    }

    public function assignScope(Request $request, DomainEntitlement $entitlement): RedirectResponse
    {
        $assignment = $entitlement->assignment;
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        $data = $request->validate([
            'scope_type' => ['required', 'string'],
            'target_id' => ['nullable', 'integer'],
        ]);

        $scopeType = ScopeType::tryFrom($data['scope_type']);

        if ($scopeType === null) {
            return $this->refuse(AccessViolation::scopeTargetRequired(ScopeType::Team));
        }

        try {
            $this->entitlements->assignScope(
                $entitlement,
                $scopeType,
                $data['target_id'] ?? null,
                $this->actor($request),
            );
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('access.show', 'Scope assigned.', $assignment->id);
    }

    public function revokeScope(Request $request, EntitlementScope $scope): RedirectResponse
    {
        $entitlement = $scope->entitlement;
        $assignment = $entitlement->assignment;
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        try {
            $this->entitlements->revokeScope($scope, $this->actor($request));
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        $remaining = EntitlementScope::query()
            ->where('domain_entitlement_id', $entitlement->id)
            ->whereNull('ended_at')
            ->exists();

        // Said plainly, because the entitlement is still current and the screen
        // would otherwise look as though nothing important had changed.
        $message = $remaining
            ? 'Scope revoked.'
            : 'Scope revoked. This entitlement now has no active scope and grants no access.';

        return $this->confirm('access.show', $message, $assignment->id);
    }

    /**
     * Set the sensitivity ceiling. Granting `restricted` requires step-up.
     */
    public function setCeiling(Request $request, DomainEntitlement $entitlement): RedirectResponse
    {
        $assignment = $entitlement->assignment;
        $this->refuseIfOutsideOrganisation($request, $assignment->organisation_id);

        $data = $request->validate(['sensitivity' => ['required', 'string']]);

        $sensitivity = Sensitivity::tryFrom($data['sensitivity']);

        if ($sensitivity === null) {
            return $this->refuse(AccessViolation::stepUpInvalid());
        }

        if ($sensitivity->requiresStepUpToGrant()) {
            return $this->beginStepUp($request, StepUpAction::GrantRestrictedSensitivity, [
                'domain_entitlement_id' => $entitlement->id,
                'sensitivity' => $sensitivity->value,
            ]);
        }

        try {
            $this->entitlements->setCeiling($entitlement, $sensitivity, $this->actor($request));
        } catch (AccessViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('access.show', 'Sensitivity level set.', $assignment->id);
    }

    /**
     * Which of the five step-up actions a grant is, if any.
     *
     * A SELF-GRANT IS ONE OF THEM WHATEVER THE ROLE. Granting yourself a
     * Business User role in a domain is still granting yourself access, and it
     * is the shape a privilege-escalation attempt takes when the obvious route
     * is closed.
     */
    private function stepUpActionForGrant(RoleCode $role, User $subject, User $actor): ?StepUpAction
    {
        if ($role === RoleCode::SystemAdministrator) {
            return StepUpAction::GrantSystemAdministrator;
        }

        if ($role === RoleCode::OrganisationAdministrator) {
            return StepUpAction::GrantOrganisationAdministrator;
        }

        if ($subject->getKey() === $actor->getKey()) {
            return StepUpAction::SelfGrant;
        }

        return null;
    }

    /** @param array<string, int|string|null> $target */
    private function beginStepUp(Request $request, StepUpAction $action, array $target): RedirectResponse
    {
        $reference = $this->stepUp->begin(
            $this->actor($request),
            $request->session()->getId(),
            $action,
            $target + ['organisation_id' => $this->organisation($request)->id],
        );

        // Only the opaque reference travels. The action, its target and its
        // parameters stay server-side.
        return redirect()->route('access.step-up.begin', ['reference' => $reference]);
    }

    /** @return array<string, mixed> */
    private function summary(RoleAssignment $assignment): array
    {
        return [
            'id' => $assignment->id,
            'userId' => $assignment->user_id,
            'name' => $assignment->user?->display_name,
            'email' => $assignment->user?->email,
            'userActive' => $assignment->user?->status->value === 'active',
            'role' => $assignment->role_code->value,
            'roleLabel' => $assignment->role_code->label(),
            'current' => $assignment->isCurrent(),
            'entitlementCount' => $assignment->current_entitlements_count ?? null,
        ];
    }

    /** @return array<string, mixed> */
    private function entitlementSummary(DomainEntitlement $entitlement): array
    {
        $currentScopes = $entitlement->scopes->filter(fn (EntitlementScope $scope): bool => $scope->isCurrent());
        $ceiling = $entitlement->ceilings->firstWhere('ended_at', null);

        return [
            'id' => $entitlement->id,
            'domainId' => $entitlement->business_domain_id,
            'domainName' => $entitlement->domain?->name,
            'domainEnabled' => $entitlement->domain?->status === DomainStatus::Enabled,
            'current' => $entitlement->isCurrent(),
            'grantedAt' => $entitlement->granted_at->toDateString(),
            'endedAt' => $entitlement->ended_at?->toDateString(),
            // The incomplete grant path. The screen must not present this as
            // though it works.
            'incomplete' => $entitlement->isCurrent() && $currentScopes->isEmpty(),
            'ceiling' => $ceiling?->sensitivity->value,
            'ceilingLabel' => $ceiling?->sensitivity->label(),
            'scopes' => $entitlement->scopes->map(fn (EntitlementScope $scope): array => [
                'id' => $scope->id,
                'type' => $scope->scope_type->value,
                'label' => $scope->scope_type->label(),
                'targetName' => $scope->team?->name ?? $scope->businessUnit?->name,
                'current' => $scope->isCurrent(),
                'assignedAt' => $scope->assigned_at->toDateString(),
                'endedAt' => $scope->ended_at?->toDateString(),
                // D-74. Said on the screen rather than left for the reader to
                // work out.
                'coversWholeDomain' => $scope->scope_type->coversWholeDomain(),
            ])->all(),
        ];
    }

    /** @return list<array<string, string>> */
    private function roleOptions(): array
    {
        return array_map(
            static fn (RoleCode $role): array => [
                'value' => $role->value,
                'label' => $role->label(),
                'summary' => $role->summary(),
            ],
            RoleCatalogue::roles(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function scopeOptions(): array
    {
        return array_map(
            static fn (ScopeType $scope): array => [
                'value' => $scope->value,
                'label' => $scope->label(),
                'description' => $scope->description(),
                'requiresTarget' => $scope->requiresTarget(),
                'targetKind' => $scope->targetColumn() === 'team_id' ? 'team' : ($scope->targetColumn() === 'business_unit_id' ? 'businessUnit' : null),
                'coversWholeDomain' => $scope->coversWholeDomain(),
            ],
            ScopeType::cases(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function sensitivityOptions(): array
    {
        return array_map(
            static fn (Sensitivity $level): array => [
                'value' => $level->value,
                'label' => $level->label(),
                'description' => $level->description(),
                'requiresStepUp' => $level->requiresStepUpToGrant(),
            ],
            Sensitivity::cases(),
        );
    }

    /** @return list<array<string, mixed>> */
    private function candidates(int $organisationId): array
    {
        // Only ACTIVE people of this organisation are offered. The refusal
        // still exists and is still tested - a picker is a convenience, never
        // the guard.
        return User::query()
            ->where('organisation_id', $organisationId)
            ->where('status', 'active')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'email'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->display_name,
                'email' => $user->email,
            ])->all();
    }
}
