<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy\Collectors;

use App\Models\User;
use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\SubjectCollector;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

/**
 * Band A. The subject's own account record, and the two tables that hold
 * sign-in state about them.
 *
 * `users` IS DISCLOSED VERBATIM. It is theirs and nobody else's.
 *
 * `password_reset_tokens` DISCLOSES THAT A TOKEN EXISTS, NEVER ITS VALUE. A
 * reset token is a live credential: handing one back in answer to an access
 * request would turn the request into an account takeover. The subject learns
 * a reset was requested and when, which is the fact about them.
 *
 * `sessions` REPORTS "NOT APPLICABLE ON THIS DEPLOYMENT" WHEN SESSIONS ARE NOT
 * STORED IN THE DATABASE, rather than "none found". The two mean different
 * things: "none found" implies the collector looked in a populated store and
 * this person had nothing there. With SESSION_DRIVER set to anything but
 * `database` the table is never written to at all, so "none found" would be
 * true by accident and misleading by implication. Approved wording, decision 3
 * of the R1.4c approval.
 *
 * If the deployment later moves to database sessions the collector enters scope
 * automatically - it reads the live driver on every call, so nothing has to be
 * remembered and the coverage test keeps passing either way.
 */
final class AccountCollector implements SubjectCollector
{
    public function tables(): array
    {
        return ['users', 'password_reset_tokens', 'sessions'];
    }

    public function collect(PrivacyRequest $request): array
    {
        return [
            $this->account($request),
            $this->resetTokens($request),
            $this->sessions($request),
        ];
    }

    private function account(PrivacyRequest $request): CollectedItem
    {
        $user = $request->subject_user_id === null
            ? null
            : User::query()->withoutGlobalScopes()->find($request->subject_user_id);

        if ($user === null) {
            return CollectedItem::describe(
                'users',
                DisclosureBand::A,
                'No SemantIQ account is linked to this request, so there is no account record to disclose. '
                .'That does not mean nothing is held: records about this person may still exist elsewhere, '
                .'and are listed below.',
            );
        }

        return CollectedItem::include(
            'users',
            DisclosureBand::A,
            'Your SemantIQ account record.',
            [
                'name' => $user->name,
                'email' => $user->email,
                'platform_role' => $user->role->label(),
                'is_auditor' => (bool) $user->is_auditor,
                'organisation_id' => $user->organisation_id,
                'business_unit_id' => $user->business_unit_id,
                'team_id' => $user->team_id,
                'entra_object_id' => $user->entra_object_id,
                'entra_tenant_id' => $user->entra_tenant_id,
                'external_reference_id' => $user->external_reference_id,
                'last_signed_in_at' => $user->last_signed_in_at?->toIso8601String(),
                'access_starts_at' => $user->access_starts_at?->toIso8601String(),
                'access_ends_at' => $user->access_ends_at?->toIso8601String(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
            $user->created_at,
        );
    }

    /**
     * The existence of a reset token, never the token.
     */
    private function resetTokens(PrivacyRequest $request): CollectedItem
    {
        $email = $request->subject_email;

        $row = DB::table('password_reset_tokens')->where('email', $email)->first();

        if ($row === null) {
            return CollectedItem::describe(
                'password_reset_tokens',
                DisclosureBand::A,
                'No password reset has been requested for your email address.',
            );
        }

        /*
         * `include` with the timestamp only. The token column is never read
         * into this array - not redacted afterwards, not selected at all.
         */
        return CollectedItem::include(
            'password_reset_tokens',
            DisclosureBand::A,
            'A password reset was requested for your email address. The reset code itself is a live '
            .'credential and is never disclosed, including to you.',
            ['requested_at' => $row->created_at],
        );
    }

    private function sessions(PrivacyRequest $request): CollectedItem
    {
        if (Config::get('session.driver') !== 'database') {
            return CollectedItem::describe(
                'sessions',
                DisclosureBand::A,
                'Not applicable on this deployment. Sign-in sessions are not stored in the database here, '
                .'so there is no session record about you to disclose.',
            );
        }

        if ($request->subject_user_id === null) {
            return CollectedItem::describe(
                'sessions',
                DisclosureBand::A,
                'No SemantIQ account is linked to this request, so no session record can belong to it.',
            );
        }

        $rows = DB::table('sessions')
            ->where('user_id', $request->subject_user_id)
            ->orderByDesc('last_activity')
            ->get();

        if ($rows->isEmpty()) {
            return CollectedItem::describe(
                'sessions',
                DisclosureBand::A,
                'No active sign-in sessions are recorded for your account.',
            );
        }

        return CollectedItem::include(
            'sessions',
            DisclosureBand::A,
            $rows->count().' active sign-in session'.($rows->count() === 1 ? '' : 's').' recorded for your account.',
            [
                'sessions' => $rows->map(fn ($row): array => [
                    'ip_address' => $row->ip_address,
                    'user_agent' => $row->user_agent,
                    'last_activity' => $row->last_activity,
                ])->all(),
            ],
        );
    }
}
