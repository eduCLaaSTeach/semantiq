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
 * The three contact fields hold a NAME or a ROLE and never a credential. They
 * appear on screen and in exported evidence.
 */
class OrganisationController extends Controller
{
    public function __construct(
        private readonly OrganisationContext $organisations,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('pages.admin.organisation', [
            'organisation' => $this->organisations->require(),
            'countries' => $this->countries(),
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
            'privacy_contact' => ['nullable', 'string', 'max:190'],
            'security_contact' => ['nullable', 'string', 'max:190'],
        ], [
            'primary_domain.regex' => 'Enter a domain such as example.com, not a full web address.',
            'default_time_zone.timezone' => 'That is not a recognised time zone. Use a form such as Asia/Singapore.',
        ]);

        $before = $this->summarise($organisation);

        /** @var User $actor */
        $actor = Auth::user();

        $organisation->forceFill(array_merge($validated, [
            'default_currency' => $validated['default_currency'] === null
                ? null
                : strtoupper($validated['default_currency']),
            'primary_country' => $validated['primary_country'] === null
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
