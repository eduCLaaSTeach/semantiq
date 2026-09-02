<?php

declare(strict_types=1);

namespace App\Modules\Organisation\Http\Controllers\Concerns;

use App\Modules\Organisation\Models\Organisation;
use App\Modules\Organisation\Support\StructureViolation;
use App\Modules\Platform\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Shared plumbing for the Organisation controllers.
 *
 * The refusal renderer is the important part. A StructureViolation is turned
 * into a stable reason plus a message written for an administrator - never the
 * raw exception. Rendering the exception message is exactly negative test 17's
 * mutation: it is how a stack trace, a framework internal or the name of a
 * record the viewer may not see reaches a browser.
 *
 * confirm() is its counterpart, and it exists because for a long time only the
 * refusal had one. A refused write said so; a SUCCESSFUL write said nothing at
 * all. On most screens the change was its own evidence - a row appeared, a pill
 * flipped - but the Company Profile re-renders identically after a save, so a
 * save that worked and a dead button looked the same. The Product Owner
 * reported it as "after Click Save nothing happens"; the save had in fact
 * worked, every time.
 */
trait InteractsWithStructure
{
    private function actor(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('semantiq_user');

        return $user;
    }

    private function organisation(Request $request): Organisation
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('semantiq_organisation');

        return $organisation;
    }

    private function refuse(StructureViolation $violation): RedirectResponse
    {
        $errors = ['structure' => $violation->getMessage(), 'reason' => $violation->reason];

        if ($violation->blockedBy !== []) {
            $errors['blockedBy'] = implode(', ', $violation->blockedBy);
        }

        return back()->withErrors($errors);
    }

    /**
     * A successful write, confirmed to the person who made it.
     *
     * The message is written for an administrator and says what happened, in
     * the past tense, naming the thing rather than the operation: "Company
     * Profile saved", not "update succeeded". It carries no identifier and no
     * record name - a name is business content, and this is the same channel a
     * refusal uses, which negative test 17 keeps free of it.
     */
    private function confirm(string $route, string $message, mixed $parameters = []): RedirectResponse
    {
        return redirect()->route($route, $parameters)->with('confirmation', $message);
    }
}
