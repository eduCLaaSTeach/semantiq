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
 * Band C, security holdings. A COUNT, never a pointer.
 *
 * `secret_references` is in the exclusion register for its detail, and here for
 * its count, and both are deliberate. The subject is entitled to know that
 * something is recorded as belonging to them. They are not entitled - and
 * neither is anybody else who files a request - to a list of where every
 * credential this system depends on lives and when it lapses.
 *
 * SEC-DEC-052 established that the credential map is more dangerous than the
 * rows it describes: it is a targeting list. A subject access request must not
 * become a way around that, and "I own three secret references" answers the
 * question about the person without publishing the map.
 */
final class SecurityHoldingsCollector implements SubjectCollector
{
    public function tables(): array
    {
        return ['secret_references'];
    }

    public function collect(PrivacyRequest $request): array
    {
        if (! Schema::hasTable('secret_references')) {
            return [CollectedItem::describe(
                'secret_references',
                DisclosureBand::C,
                'This record type does not exist on this deployment yet.',
            )];
        }

        if ($request->subject_user_id === null) {
            return [CollectedItem::describe(
                'secret_references',
                DisclosureBand::C,
                'No SemantIQ account is linked to this request, so nothing can be recorded as owned by it.',
            )];
        }

        /*
         * COUNT ONLY. No name, no location, no expiry, no id. Nothing that
         * could be used to find one.
         */
        $count = DB::table('secret_references')
            ->where('owner_user_id', $request->subject_user_id)
            ->count();

        if ($count === 0) {
            return [CollectedItem::describe(
                'secret_references',
                DisclosureBand::C,
                'You are not recorded as the owner of any stored credential reference.',
            )];
        }

        return [CollectedItem::describe(
            'secret_references',
            DisclosureBand::C,
            'You are recorded as the owner of '.$count.' stored credential reference'
            .($count === 1 ? '' : 's').'. Which credentials they are, where they are held and when they '
            .'expire are not disclosed to anybody through this route, including to you: taken together that '
            .'list is a map of the system\'s defences rather than personal data about you.',
        )];
    }
}
