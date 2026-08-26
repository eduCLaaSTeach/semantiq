<?php

declare(strict_types=1);

namespace App\Modules\Governance\Privacy\Collectors;

use App\Modules\Audit\Models\AuditEvent;
use App\Modules\Governance\Enums\DisclosureBand;
use App\Modules\Governance\Models\PrivacyRequest;
use App\Modules\Governance\Privacy\CollectedItem;
use App\Modules\Governance\Privacy\SubjectCollector;

/**
 * Band B. The audit trail, as it concerns this subject.
 *
 * THE TABLE IS NEVER RELEASED RAW. What is disclosed is a rendered summary per
 * event: when, what action, what outcome. Three things are held back by
 * default, and each for its own reason:
 *
 *   before_summary / after_summary  They describe the record that changed,
 *                                   which is frequently SOMEBODY ELSE'S. An
 *                                   administrator changing Bob's role produces
 *                                   an after_summary full of Bob.
 *
 *   ip_address                      Personal data in its own right, disclosed
 *                                   under its own permission by SEC-DEC-063,
 *                                   and rarely what an access request is
 *                                   actually about.
 *
 *   reason                          Free text a person typed about why they
 *                                   made a high-risk change. May name anybody.
 *
 * A reviewer may include a NAMED EVENT after looking at it. They cannot include
 * the table. That asymmetry is the control: including one event is a decision
 * about one event, and including the column would be a decision about every
 * event anybody ever wrote.
 *
 * WHY EVENTS ARE MATCHED ON BOTH ACTOR AND RESOURCE. An audit event concerns
 * this person either because they DID it or because it was done TO them.
 * Collecting only the first would silently drop every administrative action
 * taken against their account, which is the half a subject is most likely to be
 * asking about.
 */
final class ActivityCollector implements SubjectCollector
{
    /**
     * How many events to render individually before summarising the remainder.
     *
     * Not a cap on what is collected - the count below is always the true
     * total. It bounds how much is written into one response row, because a
     * trail of ten thousand entries rendered in full is not a readable answer
     * to anybody and would put the whole trail in one JSON column.
     */
    private const DETAILED = 200;

    public function tables(): array
    {
        return ['audit_events'];
    }

    public function collect(PrivacyRequest $request): array
    {
        if ($request->subject_user_id === null) {
            return [$this->withoutAccount($request)];
        }

        $userId = $request->subject_user_id;

        $query = AuditEvent::query()
            ->where(function ($q) use ($userId): void {
                $q->where('actor_user_id', $userId)
                    ->orWhere(function ($inner) use ($userId): void {
                        $inner->where('resource_type', 'user')->where('resource_id', (string) $userId);
                    });
            })
            ->orderByDesc('occurred_at');

        $total = (clone $query)->count();

        if ($total === 0) {
            return [CollectedItem::describe(
                'audit_events',
                DisclosureBand::B,
                'No activity is recorded against your account.',
            )];
        }

        $events = $query->limit(self::DETAILED)->get();

        return [CollectedItem::include(
            'audit_events',
            DisclosureBand::B,
            $this->headline($total),
            [
                'total_events' => $total,
                'shown' => $events->count(),
                'withheld_by_default' => [
                    'before and after summaries' => 'They describe the record that changed, which is often '
                        .'somebody else\'s.',
                    'network address' => 'Disclosed under its own permission, and rarely what a request is about.',
                    'reason text' => 'Free text that may name another person.',
                ],
                'events' => $events->map(fn (AuditEvent $event): array => [
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                    'action' => $event->action,
                    'module' => $event->module,
                    'outcome' => $event->outcome?->value,
                    'resource_type' => $event->resource_type,
                    'you_were' => $event->actor_user_id === $userId ? 'the person who acted' : 'the subject',
                ])->all(),
            ],
            $events->first()?->occurred_at,
        )];
    }

    private function headline(int $total): string
    {
        $shown = min($total, self::DETAILED);

        $line = $total.' entr'.($total === 1 ? 'y is' : 'ies are').' recorded in the audit trail about you, '
            .'either because you performed the action or because it was performed on your account.';

        if ($total > $shown) {
            $line .= ' The '.$shown.' most recent are listed individually.';
        }

        return $line.' Each entry shows when, what and the outcome. The before and after detail, the network '
            .'address and any reason text are held back by default, because they frequently describe another '
            .'person; a reviewer may release a specific entry in full after looking at it.';
    }

    private function withoutAccount(PrivacyRequest $request): CollectedItem
    {
        return CollectedItem::describe(
            'audit_events',
            DisclosureBand::B,
            'No SemantIQ account is linked to this request, so audit entries cannot be matched to it '
            .'automatically. If this person previously held an account, a reviewer can link it to the request '
            .'and re-run the assembly.',
        );
    }
}
