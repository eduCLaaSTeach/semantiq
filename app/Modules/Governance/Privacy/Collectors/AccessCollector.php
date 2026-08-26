<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy\Collectors;

use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\SubjectCollector;
use Illuminate\Support\Facades\DB;

/**
 * Band B. What access this person holds, and the reviews they were subject to.
 *
 * All three tables are records ABOUT the subject, so all three are disclosed.
 * What is carefully NOT disclosed is who granted or decided - that person is
 * band C data belonging to them, not to the subject asking. The queries below
 * therefore never join to the granting user, and the rendered rows never carry
 * a `granted_by` or `decided_by` field.
 *
 * `access_review_items.note` IS FREE TEXT and could name anybody. It is
 * disclosed because a note written ABOUT this person, in a review OF this
 * person, is squarely their own data and withholding it would under-answer the
 * request. A reviewer may narrow it if a specific note names a third party.
 */
final class AccessCollector implements SubjectCollector
{
    public function tables(): array
    {
        return ['domain_entitlements', 'user_roles', 'access_review_items'];
    }

    public function collect(PrivacyRequest $request): array
    {
        if ($request->subject_user_id === null) {
            return array_map(
                fn (string $table): CollectedItem => CollectedItem::describe(
                    $table,
                    DisclosureBand::B,
                    'No SemantIQ account is linked to this request, so no access record can belong to it.',
                ),
                $this->tables(),
            );
        }

        return [
            $this->entitlements($request->subject_user_id),
            $this->roles($request->subject_user_id),
            $this->reviewItems($request->subject_user_id),
        ];
    }

    private function entitlements(int $userId): CollectedItem
    {
        $rows = DB::table('domain_entitlements')->where('user_id', $userId)->get();

        if ($rows->isEmpty()) {
            return CollectedItem::describe(
                'domain_entitlements',
                DisclosureBand::B,
                'You hold no business-domain entitlements. Holding a platform role alone never grants access '
                .'to business data in SemantIQ.',
            );
        }

        return CollectedItem::include(
            'domain_entitlements',
            DisclosureBand::B,
            'You hold '.$rows->count().' business-domain entitlement'.($rows->count() === 1 ? '' : 's').'.',
            [
                'entitlements' => $rows->map(fn ($row): array => [
                    'business_domain' => $row->domain,
                    'scope' => $row->scope,
                    'granted_at' => $row->created_at,
                ])->all(),
            ],
        );
    }

    private function roles(int $userId): CollectedItem
    {
        $rows = DB::table('user_roles')
            ->join('roles', 'roles.id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $userId)
            ->select('roles.name', 'roles.code', 'user_roles.created_at')
            ->get();

        if ($rows->isEmpty()) {
            return CollectedItem::describe(
                'user_roles',
                DisclosureBand::B,
                'You hold no additional access roles beyond your platform tier.',
            );
        }

        return CollectedItem::include(
            'user_roles',
            DisclosureBand::B,
            'You hold '.$rows->count().' additional access role'.($rows->count() === 1 ? '' : 's').'.',
            [
                'roles' => $rows->map(fn ($row): array => [
                    'role' => $row->name,
                    'key' => $row->key,
                    'assigned_at' => $row->created_at,
                ])->all(),
            ],
        );
    }

    private function reviewItems(int $userId): CollectedItem
    {
        $rows = DB::table('access_review_items')->where('user_id', $userId)->get();

        if ($rows->isEmpty()) {
            return CollectedItem::describe(
                'access_review_items',
                DisclosureBand::B,
                'Your access has not been examined in an access review.',
            );
        }

        return CollectedItem::include(
            'access_review_items',
            DisclosureBand::B,
            'Your access was examined in '.$rows->count().' access review'.($rows->count() === 1 ? '' : 's')
            .'. The decision and any note recorded about you are shown; who made the decision is not, because '
            .'that is personal data about them.',
            [
                'reviews' => $rows->map(fn ($row): array => [
                    'recorded_as' => $row->subject_label,
                    'decision' => $row->decision,
                    'note' => $row->note,
                    'decided_at' => $row->decided_at,
                ])->all(),
            ],
        );
    }
}
