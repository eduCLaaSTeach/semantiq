<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy\Collectors;

use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\SubjectCollector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Band D. Free text that may name a person.
 *
 * These are the columns somebody typed into: a contact name on the organisation
 * profile, a justification on a sovereignty exception, the assertion a subject
 * wrote on a correction note. They are unbounded by definition, so the safe
 * assumption is that any of them may name anybody.
 *
 * THE ONE PLACE FREE TEXT IS DISCLOSED VERBATIM is where the subject wrote it
 * themselves. Their own words on their own request are theirs; describing them
 * back would be absurd.
 *
 * EVERYWHERE ELSE IT IS MATCHED, NOT READ. The collector asks whether the
 * subject's name appears in a contact field - which is a fact about them - and
 * reports that. It does not return the surrounding text, because the
 * surrounding text is the organisation's and may name others.
 *
 * WHY NOT SEARCH EVERY FREE-TEXT COLUMN FOR THE SUBJECT'S NAME? Because a
 * substring match on a name is both wrong and dangerous. "Sam" matches
 * "Samantha" and "sample"; a common surname matches half the table. A search
 * that guesses would either miss real matches or return other people's records
 * on a coincidence, and returning somebody else's record by accident is the
 * exact failure this whole feature is built to prevent. Explicitly-named
 * contact fields are matched; narrative prose is reported as a known limit
 * instead of guessed at.
 */
final class NarrativeCollector implements SubjectCollector
{
    /**
     * Only `privacy_request_records` is CLAIMED here.
     *
     * `privacy_requests` is claimed by `AttributionCollector`, which reports at
     * band C that this person HANDLED requests belonging to other people. This
     * collector renders the subject's own request back to them, and does so
     * from the request object it is already given rather than by querying -
     * so it needs no claim on that table.
     *
     * A collector's CLAIM says which table it is authoritative for. An item's
     * `sourceTable` says where the fact came from. They are usually the same
     * and here deliberately are not, because one table legitimately produces
     * two different facts about two different people.
     */
    public function tables(): array
    {
        return ['privacy_request_records'];
    }

    public function collect(PrivacyRequest $request): array
    {
        return [
            $this->ownWords($request),
            $this->contactFields($request),
            $this->knownLimit(),
        ];
    }

    /**
     * The subject's own request, in their own words.
     */
    private function ownWords(PrivacyRequest $request): CollectedItem
    {
        return CollectedItem::include(
            'privacy_requests',
            DisclosureBand::D,
            'The request you made, as it was recorded.',
            [
                'reference' => $request->reference,
                'type' => $request->request_type->label(),
                'received_at' => $request->received_at?->toIso8601String(),
                'received_channel' => $request->received_channel,
                'recorded_name' => $request->subject_name,
                'recorded_email' => $request->subject_email,
                'recorded_reference' => $request->subject_reference,
            ],
            $request->received_at,
        );
    }

    /**
     * Whether the subject is named in an organisation contact field.
     *
     * Matched exactly against the recorded name, not searched for as a
     * substring. See the class comment for why.
     */
    private function contactFields(PrivacyRequest $request): CollectedItem
    {
        $named = [];
        $name = trim($request->subject_name);

        if ($name !== '' && Schema::hasTable('organisations')) {
            foreach (['data_owner' => 'data owner', 'privacy_contact' => 'privacy contact', 'security_contact' => 'security contact'] as $column => $label) {
                if (! Schema::hasColumn('organisations', $column)) {
                    continue;
                }

                $hit = DB::table('organisations')->where($column, $name)->exists();

                if ($hit) {
                    $named[] = $label;
                }
            }
        }

        if ($named === []) {
            return CollectedItem::describe(
                'privacy_request_records',
                DisclosureBand::D,
                'You are not named in any of the accountability contact fields on the organisation profile.',
            );
        }

        return CollectedItem::describe(
            'privacy_request_records',
            DisclosureBand::D,
            'You are named on the organisation profile as the '.implode(' and the ', $named).'.',
        );
    }

    /**
     * An honest statement of what this collector cannot do.
     *
     * A response that quietly omitted this would imply completeness it does not
     * have. Saying it plainly lets a reviewer decide whether a manual search is
     * warranted for a particular request.
     */
    private function knownLimit(): CollectedItem
    {
        return CollectedItem::withheld(
            'privacy_request_records',
            DisclosureBand::D,
            'Narrative text written by other people - the reason given for a change, the justification on an '
            .'exception, a note on a review - is not searched for your name. Matching a name as a substring '
            .'returns the wrong records in both directions, and returning somebody else\'s record on a '
            .'coincidence is the failure this process exists to prevent. A reviewer can search a specific '
            .'record by hand if you believe one names you.',
        );
    }
}
