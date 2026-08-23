<?php

declare(strict_types=1);

namespace App\Modules\Security\Http\Requests;

use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

/**
 * Validates a change to an existing secret reference. Feature ADM-012.
 *
 * Identical to the create rules apart from the uniqueness check, which has to
 * ignore the row being edited or saving a reference without renaming it would
 * fail against itself. Subclassing rather than copying, so the credential check
 * and every field rule stay in one place: the day somebody adds a field to one
 * form and not the other is the day the other form stops guarding it.
 */
class UpdateSecretReferenceRequest extends StoreSecretReferenceRequest
{
    protected function uniqueNameRule(): Unique
    {
        return Rule::unique('secret_references', 'name')
            ->where('organisation_id', app(OrganisationContext::class)->currentId())
            ->ignore($this->route('secretReference'));
    }
}
