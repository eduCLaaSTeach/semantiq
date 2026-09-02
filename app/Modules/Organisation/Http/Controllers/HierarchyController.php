<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers;

use App\Modules\Organisation\Http\Controllers\Concerns\InteractsWithStructure;
use App\Modules\Organisation\Models\ManagementRelationship;
use App\Modules\Organisation\Services\ManagementService;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The management hierarchy.
 *
 * P1-05 will walk this chain to resolve manager scope. It resolves nothing here:
 * this screen records who reports to whom and answers no question about access.
 */
final class HierarchyController
{
    use InteractsWithStructure;

    public function __construct(private readonly ManagementService $management) {}

    public function index(Request $request): Response
    {
        $organisation = $this->organisation($request);

        $current = ManagementRelationship::query()
            ->where('organisation_id', $organisation->id)
            ->whereNull('effective_to')
            ->get()
            ->keyBy('user_id');

        $users = User::query()
            ->where('organisation_id', $organisation->id)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'email', 'status']);

        $names = $users->pluck('display_name', 'id');

        return Inertia::render('Organisation/Hierarchy', [
            'people' => $users
                ->map(function (User $user) use ($current, $names): array {
                    $relationship = $current->get($user->id);

                    return [
                        'id' => $user->id,
                        'name' => $user->display_name,
                        'email' => $user->email,
                        'manager' => $relationship === null ? null : $names->get($relationship->manager_id),
                        'managerId' => $relationship?->manager_id,
                    ];
                })
                ->all(),
        ]);
    }

    public function setManager(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer'],
            'manager_id' => ['required', 'integer'],
        ]);

        $subject = User::query()->find($validated['user_id']);
        $manager = User::query()->find($validated['manager_id']);

        if ($subject === null || $manager === null) {
            return $this->refuse(StructureViolation::because('not_found', 'That user does not exist.'));
        }

        try {
            $this->management->setManager($subject, $manager, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('organisation.hierarchy', 'Manager recorded. Any previous reporting line is ended and kept.');
    }

    public function clearManager(Request $request, User $user): RedirectResponse
    {
        try {
            $this->management->clearManager($user, $this->actor($request));
        } catch (StructureViolation $violation) {
            return $this->refuse($violation);
        }

        return $this->confirm('organisation.hierarchy', 'Manager cleared. The record of the previous reporting line is kept.');
    }
}
