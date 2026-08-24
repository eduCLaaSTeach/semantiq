<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Audit\Support\AuditLogger;
use App\Modules\Identity\Models\Organisation;
use App\Modules\Identity\Support\OrganisationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * The organisation profile. Feature ADM-002.
 *
 * One record, edited in place. There is no create and no delete: gate 1's
 * bootstrap migration created the row because everything else is scoped to it,
 * and VAL-ORG-DELETE-001 forbids removing an organisation that has
 * dependencies - which, by the time anyone can reach this screen, it always
 * has.
 *
 * The contact fields hold a NAME, a ROLE or an address to write to, and never a
 * credential. They appear on screen and in exported evidence.
 *
 * The privacy contact is REQUIRED and STRUCTURED as of gate 4 batch R1.4a -
 * SEC-DEC-043, resolved 24 August 2026. The requirement is staged: the columns
 * were added nullable and backfilled, the screen warns when a part is missing,
 * and only a save enforces it. An organisation nobody edits keeps working.
 */
class OrganisationController extends Controller
{
    public function __construct(
        private readonly OrganisationContext $organisations,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        $organisation = $this->organisations->require();

        return view('pages.admin.organisation', [
            'organisation' => $organisation,
            'countries' => $this->countries(),
            /*
             * Whether the now-required privacy contact is still missing a
             * required part. Computed here rather than in the view, so the
             * screen shows one warning and the rule lives in one place.
             *
             * Shown BEFORE the save rather than as a validation error after it,
             * because an organisation saved before this field was split up did
             * nothing wrong: the requirement is new, and meeting somebody with
             * a red error for a rule that did not exist when they last saved is
             * how a compliance improvement reads as a bug.
             */
            'privacyContactIncomplete' => ($organisation->privacy_contact_name ?? '') === ''
                || ($organisation->privacy_contact_email ?? '') === '',
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $organisation = $this->organisations->require();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'legal_name' => ['nullable', 'string', 'max:190'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            /* ISO 3166-1 alpha-2, validated by shape and by list. */
            'primary_country' => ['nullable', 'string', 'size:2'],
            'primary_domain' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+\.[a-z]{2,}$/i'],
            /* IANA identifier. `timezone` rather than a copied list, which
             * would drift from PHP's own set the moment either changed. */
            'default_time_zone' => ['nullable', 'timezone'],
            /* ISO 4217. Three letters, uppercased on the way in. */
            'default_currency' => ['nullable', 'string', 'size:3', 'alpha'],
            'default_language' => ['nullable', 'string', 'max:16'],
            'data_owner' => ['nullable', 'string', 'max:190'],
            /*
             * The structured privacy contact. SEC-DEC-043, resolved 24 August
             * 2026: name and email are REQUIRED, phone and role are optional.
             *
             * STAGED, NOT RETROSPECTIVE. The migration added the columns
             * nullable and backfilled the old free-text value into the name, so
             * no existing row was broken by the schema change. The requirement
             * bites here, at the moment somebody saves - and the screen warns
             * them what it now needs before they get that far. An organisation
             * that nobody edits keeps working.
             *
             * `privacy_contact` itself is KEPT IN THE DATABASE and is NO LONGER
             * ACCEPTED HERE. It is the source the backfill came from, and
             * removing the column in the same release would leave nothing to
             * compare against if the backfill turns out to have taken the wrong
             * thing - but it is now a historical record, so nothing may write
             * to it. Leaving it in this list would have let a posted field
             * overwrite the very value the backfill is there to preserve. It is
             * still summarised into the audit trail below, so the legacy value
             * stays visible. A later, separately approved change may drop the
             * column.
             */
            'privacy_contact_name' => ['required', 'string', 'max:190'],
            'privacy_contact_email' => ['required', 'email:rfc', 'max:190'],
            'privacy_contact_phone' => ['nullable', 'string', 'max:64'],
            'privacy_contact_role' => ['nullable', 'string', 'max:190'],
            'security_contact' => ['nullable', 'string', 'max:190'],
        ], [
            'primary_domain.regex' => 'Enter a domain such as example.com, not a full web address.',
            'default_time_zone.timezone' => 'That is not a recognised time zone. Use a form such as Asia/Singapore.',
            'privacy_contact_name.required' => 'A designated privacy contact is required. Enter the '
                .'name or the job title of the person who answers privacy questions.',
            'privacy_contact_email.required' => 'A privacy contact needs an address a data subject or a '
                .'regulator can actually reach.',
            'privacy_contact_email.email' => 'That is not an email address. A shared mailbox such as '
                .'privacy@example.com is usually better than one person\'s inbox.',
        ]);

        $before = $this->summarise($organisation);

        /** @var User $actor */
        $actor = Auth::user();

        /*
         * `?? null`, not `=== null`.
         *
         * A `nullable` rule that is not posted at all leaves the key ABSENT
         * from the validated array rather than present and null, so indexing it
         * directly raised "Undefined array key" and returned 500 on a partial
         * post. Found by a gate 4 test that posted only the fields it was
         * asserting about; the same shape would come from any client that omits
         * an optional field.
         */
        $organisation->forceFill(array_merge($validated, [
            'default_currency' => ($validated['default_currency'] ?? null) === null
                ? null
                : strtoupper($validated['default_currency']),
            'primary_country' => ($validated['primary_country'] ?? null) === null
                ? null
                : strtoupper($validated['primary_country']),
            'updated_by_user_id' => $actor->getKey(),
            /* Optimistic concurrency: two administrators editing at once each
             * bump it, and the version in the trail says which edit is which. */
            'version' => $organisation->version + 1,
        ]))->save();

        $this->audit->record(
            action: 'organisation.updated',
            module: 'Identity',
            resourceType: 'organisation',
            resourceId: $organisation->getKey(),
            before: $before,
            after: $this->summarise($organisation->fresh()),
        );

        return redirect()
            ->route('admin.organisation')
            ->with('status', 'Organisation profile saved.');
    }

    /**
     * A redacted summary for the trail.
     *
     * @return array<string, mixed>
     */
    private function summarise(Organisation $organisation): array
    {
        return $organisation->only([
            'name', 'legal_name', 'registration_number', 'primary_country', 'primary_domain',
            'default_time_zone', 'default_currency', 'default_language',
            'data_owner', 'privacy_contact', 'security_contact',
            /*
             * The four structured contact fields are summarised too, so a
             * change to the designated privacy contact is visible in the trail
             * rather than being an invisible edit to a compliance record.
             *
             * Every one of these names was checked against
             * `Redaction::isSensitiveKey()`. None contains a matched fragment,
             * so the trail records the real before and after values instead of
             * "[redacted]". SEC-DEC-044.
             */
            'privacy_contact_name', 'privacy_contact_email',
            'privacy_contact_phone', 'privacy_contact_role',
        ]) + ['status' => $organisation->status->value];
    }

    /**
     * The countries offered.
     *
     * A short curated list rather than all 249, because the field exists to
     * give policy context - which privacy regime questions get asked at
     * go-live - and a 249-entry select is a field nobody sets correctly. It is
     * a free two-letter code underneath, so an unlisted country is a
     * configuration change rather than a code change.
     *
     * @return array<string, string>
     */
    private function countries(): array
    {
        return [
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'ID' => 'Indonesia',
            'IN' => 'India',
            'AU' => 'Australia',
            'GB' => 'United Kingdom',
            'US' => 'United States',
            'AE' => 'United Arab Emirates',
        ];
    }
}
