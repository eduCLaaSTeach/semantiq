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
 * Band C. The subject's name attached to SOMEBODY ELSE'S record.
 *
 * THIS IS THE DESIGN PROBLEM OF THE WHOLE FEATURE. "Alice approved Bob's
 * Finance entitlement" is personal data about Alice AND about Bob. Releasing it
 * verbatim to Alice discloses Bob, who asked for nothing and consented to
 * nothing. Withholding it entirely under-answers a lawful request from Alice.
 *
 * Decision D5 resolves it: disclose the FACT WITHOUT THE SECOND PERSON.
 *
 *      "You approved a business-domain entitlement on 3 March 2026."
 *
 * not
 *
 *      "You approved Bob Smith's Finance entitlement on 3 March 2026."
 *
 * THE STRUCTURAL DEFENCE, and the reason this class is shaped the way it is:
 * the query below selects a COUNT and a DATE RANGE. It does not select the
 * owning row, does not join to any user, and never loads the other party at
 * all. The rendering function is therefore given nothing it could leak, so a
 * template mistake cannot disclose an identity the function never held. That is
 * a stronger guarantee than remembering not to print a field.
 *
 * HOW A TABLE JOINS THIS SET. Add it to `ATTRIBUTED`, naming the columns that
 * point at a person. Nothing else is needed, and nothing else may be added:
 * there is deliberately no way to declare "and also show these columns".
 */
final class AttributionCollector implements SubjectCollector
{
    /**
     * Table => [attribution columns, what the subject is told they did].
     *
     * The phrasing is written for the subject to read, in the second person,
     * and never names the record acted upon where naming it would identify
     * somebody: "a business unit", not "the Finance business unit".
     *
     * @var array<string, array{columns: list<string>, did: string}>
     */
    private const ATTRIBUTED = [
        'organisations' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id'],
            'did' => 'changed the organisation profile',
        ],
        'business_units' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id', 'manager_user_id'],
            'did' => 'created, changed or were recorded as the manager of a business unit',
        ],
        'teams' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id', 'lead_user_id'],
            'did' => 'created, changed or were recorded as the lead of a team',
        ],
        'roles' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id'],
            'did' => 'created or changed a role definition',
        ],
        'access_reviews' => [
            'columns' => ['opened_by_user_id', 'created_by_user_id', 'updated_by_user_id'],
            'did' => 'opened or changed an access review',
        ],
        'system_settings' => [
            'columns' => ['updated_by_user_id'],
            'did' => 'changed a system setting',
        ],
        'feature_flags' => [
            'columns' => ['updated_by_user_id'],
            'did' => 'changed a feature flag',
        ],
        'security_policies' => [
            'columns' => ['updated_by_user_id'],
            'did' => 'changed a security policy',
        ],
        'data_protection_profiles' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id', 'approved_by_user_id'],
            'did' => 'created, changed or approved a data protection profile',
        ],
        'data_sovereignty_profiles' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id', 'approved_by_user_id'],
            'did' => 'created, changed or approved a data sovereignty profile',
        ],
        'personal_data_categories' => [
            'columns' => ['updated_by_user_id'],
            'did' => 'changed an entry in the personal data register',
        ],
        'retention_policies' => [
            'columns' => ['created_by_user_id', 'updated_by_user_id', 'approved_by_user_id'],
            'did' => 'set or approved a retention policy',
        ],
        'sovereignty_exceptions' => [
            'columns' => ['requested_by_user_id', 'decided_by_user_id', 'revoked_by_user_id', 'updated_by_user_id'],
            'did' => 'requested, decided or revoked a sovereignty exception',
        ],
        'privacy_requests' => [
            'columns' => ['identity_verified_by_user_id', 'reviewed_by_user_id', 'released_by_user_id', 'created_by_user_id', 'updated_by_user_id'],
            'did' => 'handled a privacy request',
        ],
        'privacy_correction_notes' => [
            'columns' => ['decided_by_user_id', 'created_by_user_id'],
            'did' => 'recorded or decided a correction note',
        ],
    ];

    public function tables(): array
    {
        return array_keys(self::ATTRIBUTED);
    }

    public function collect(PrivacyRequest $request): array
    {
        if ($request->subject_user_id === null) {
            return array_map(
                fn (string $table): CollectedItem => CollectedItem::describe(
                    $table,
                    DisclosureBand::C,
                    'No SemantIQ account is linked to this request, so no administrative action can be '
                    .'attributed to it.',
                ),
                $this->tables(),
            );
        }

        $items = [];

        foreach (self::ATTRIBUTED as $table => $spec) {
            $items[] = $this->describeInvolvement($table, $spec, $request->subject_user_id);
        }

        return $items;
    }

    /**
     * @param  array{columns: list<string>, did: string}  $spec
     */
    private function describeInvolvement(string $table, array $spec, int $userId): CollectedItem
    {
        /*
         * A table this deployment has not migrated yet is not an error here.
         * Assembly must survive the window where code is ahead of schema, in
         * the same way every governance screen does.
         */
        if (! Schema::hasTable($table)) {
            return CollectedItem::describe(
                $table,
                DisclosureBand::C,
                'This record type does not exist on this deployment yet, so nothing can be attributed to you '
                .'in it.',
            );
        }

        $columns = array_values(array_filter(
            $spec['columns'],
            fn (string $column): bool => Schema::hasColumn($table, $column),
        ));

        if ($columns === []) {
            return CollectedItem::describe(
                $table,
                DisclosureBand::C,
                'This record type does not record who acted on it, so nothing can be attributed to you in it.',
            );
        }

        /*
         * COUNT AND DATE RANGE ONLY.
         *
         * No row is selected, no user is joined, and the other party is never
         * loaded. What comes back cannot identify anybody, so what is rendered
         * below cannot disclose anybody.
         */
        $query = DB::table($table)->where(function ($q) use ($columns, $userId): void {
            foreach ($columns as $column) {
                $q->orWhere($column, $userId);
            }
        });

        $count = (clone $query)->count();

        if ($count === 0) {
            return CollectedItem::describe(
                $table,
                DisclosureBand::C,
                'You have not '.$spec['did'].'.',
            );
        }

        $latest = Schema::hasColumn($table, 'updated_at')
            ? (clone $query)->max('updated_at')
            : null;

        $when = $latest === null ? '' : ' The most recent was on '.$this->readableDate($latest).'.';

        return CollectedItem::describe(
            $table,
            DisclosureBand::C,
            'You '.$spec['did'].' on '.$count.' occasion'.($count === 1 ? '' : 's').'.'.$when
            .' The records themselves are not listed, because they belong to the organisation and may name '
            .'other people who have not asked for their data.',
        );
    }

    private function readableDate(string $timestamp): string
    {
        $stamp = strtotime($timestamp);

        return $stamp === false ? $timestamp : date('j F Y', $stamp);
    }
}
