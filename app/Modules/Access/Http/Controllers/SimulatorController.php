<?php

declare(strict_types=1);

namespace App\Modules\Access\Http\Controllers;

use App\Modules\Access\Engine\AccessEngine;
use App\Modules\Access\Engine\AccessExplanation;
use App\Modules\Access\Engine\AccessQuestion;
use App\Modules\Access\Engine\GrantPathReference;
use App\Modules\Access\Engine\ResourceReference;
use App\Modules\Access\Http\Controllers\Concerns\InteractsWithAccess;
use App\Modules\Access\Support\ReasonNarrator;
use App\Modules\Access\Support\ScopeType;
use App\Modules\Access\Support\Sensitivity;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Organisation\Models\BusinessUnit;
use App\Modules\Organisation\Models\Team;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Access Simulator.
 *
 * IT HOLDS NO AUTHORIZATION LOGIC. It builds an AccessQuestion, calls
 * AccessEngine::explain(), and renders the result. A simulator with its own
 * logic gives confident answers that are wrong exactly when the two
 * implementations have drifted - which is exactly when somebody most needs the
 * truth.
 *
 * IT USES explain(), NOT decide(). An administrator about to revoke a grant is
 * asking "would this person still have access afterwards?", and a first-match
 * answer would tell them Finance access comes from the Manager path while an
 * Executive path also grants it - leading them to make a change they did not
 * intend.
 *
 * IT WRITES NOTHING AND SHOWS NO BUSINESS VALUES. It answers what WOULD be
 * visible, never what is in a record. And a simulation is NEVER authorization
 * to write: the save paths in AccessController re-authorize independently at
 * commit time.
 *
 * RAW REASON CODES NEVER REACH THIS SCREEN. Every explanation is the
 * business-language sentence ReasonNarrator derives from the code; the code
 * itself stays internal, for evidence and tests.
 */
final class SimulatorController
{
    use InteractsWithAccess;

    public function __construct(private readonly AccessEngine $engine) {}

    public function show(Request $request): Response
    {
        return Inertia::render('Access/Simulator', $this->formData($request) + [
            'result' => null,
        ]);
    }

    public function simulate(Request $request): Response|RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'business_domain_id' => ['required', 'integer', 'exists:business_domains,id'],
            'sensitivity' => ['required', 'string'],
            'record_owner' => ['nullable', 'string'],
            'team_id' => ['nullable', 'integer'],
            'business_unit_id' => ['nullable', 'integer'],
        ]);

        $organisation = $this->organisation($request);

        $subject = User::query()->findOrFail($data['user_id']);
        $domain = BusinessDomain::query()->findOrFail($data['business_domain_id']);

        // An administrator may simulate a person in THEIR OWN organisation
        // only. The simulator can never exceed the caller.
        if ($subject->organisation_id !== null && $subject->organisation_id !== $organisation->id) {
            abort(404);
        }

        $this->refuseIfOutsideOrganisation($request, $domain->organisation_id);

        $sensitivity = Sensitivity::tryFrom($data['sensitivity']) ?? Sensitivity::Standard;

        // The record being asked about, described structurally. There is no
        // business data in P1-05, so this is what a scope is tested against -
        // whose record it is, which team, which business unit.
        $resource = new ResourceReference(
            subjectUserId: ($data['record_owner'] ?? '') === 'self' ? $subject->getKey() : null,
            teamIds: isset($data['team_id']) && $data['team_id'] !== null ? [(int) $data['team_id']] : [],
            businessUnitIds: isset($data['business_unit_id']) && $data['business_unit_id'] !== null ? [(int) $data['business_unit_id']] : [],
            organisationId: $organisation->id,
        );

        $explanation = $this->engine->explain(AccessQuestion::businessData(
            $subject,
            'simulate',
            $domain->id,
            $resource,
            $sensitivity,
            $subject->organisation_id,
        ));

        return Inertia::render('Access/Simulator', $this->formData($request) + [
            'result' => $this->present($explanation, $subject, $domain, $sensitivity),
            'submitted' => $data,
        ]);
    }

    /** @return array<string, mixed> */
    private function present(
        AccessExplanation $explanation,
        User $subject,
        BusinessDomain $domain,
        Sensitivity $sensitivity,
    ): array {
        return [
            'allowed' => $explanation->allowed,
            'subjectName' => $subject->display_name,
            'domainName' => $domain->name,
            'sensitivityLabel' => $sensitivity->label(),
            // The sentence, never the code. §11.4 and N-EN4.
            'explanation' => $explanation->explanation(),
            'paths' => array_map(
                fn (GrantPathReference $path): array => [
                    'assignmentId' => $path->roleAssignmentId,
                    'roleLabel' => $path->role->label(),
                    'scopeLabel' => $path->scopeType->label(),
                    'ceilingLabel' => $path->ceiling->label(),
                    'sentence' => $path->narrate($domain->name),
                    // The question an administrator actually asks before
                    // revoking: would access survive without this one?
                    'accessSurvivesWithoutThis' => $explanation->remainsAllowedWithout($path->roleAssignmentId),
                    // D-74, said where the answer is given.
                    'coversWholeDomain' => $path->scopeType->coversWholeDomain(),
                ],
                $explanation->authorisingPaths,
            ),
            'blockers' => array_map(
                static fn (array $failure): array => [
                    'sentence' => $failure['sentence'],
                    'detail' => $failure['detail'],
                ],
                $explanation->narratedFailures(),
            ),
        ];
    }

    /** @return array<string, mixed> */
    private function formData(Request $request): array
    {
        $organisationId = $this->organisation($request)->id;

        return [
            'people' => User::query()
                ->where('organisation_id', $organisationId)
                ->orderBy('display_name')
                ->get(['id', 'display_name', 'email', 'status'])
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'email' => $user->email,
                    // Shown because an inactive account is denied for a reason
                    // the administrator can act on, and hiding them would make
                    // that denial look like a bug.
                    'active' => $user->status->value === 'active',
                ])->all(),
            'domains' => BusinessDomain::query()
                ->where('organisation_id', $organisationId)
                ->orderBy('name')
                ->get(['id', 'name', 'status'])
                ->map(fn (BusinessDomain $domain): array => [
                    'id' => $domain->id,
                    'name' => $domain->name,
                    'enabled' => $domain->status->value === 'enabled',
                ])->all(),
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
            'sensitivities' => array_map(
                static fn (Sensitivity $level): array => [
                    'value' => $level->value,
                    'label' => $level->label(),
                ],
                Sensitivity::cases(),
            ),
            'scopeEquivalenceNote' => 'Domain and Organisation scope grant the same records today. '
                .'Domain is reserved for a future split of a domain across parts of the business.',
            // The legend, so an administrator can see what the simulator can
            // tell them before they run anything.
            'reasons' => ReasonNarrator::all(),
            'scopeTypes' => array_map(
                static fn (ScopeType $scope): array => [
                    'value' => $scope->value,
                    'label' => $scope->label(),
                    'description' => $scope->description(),
                ],
                ScopeType::cases(),
            ),
        ];
    }
}
