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
}
