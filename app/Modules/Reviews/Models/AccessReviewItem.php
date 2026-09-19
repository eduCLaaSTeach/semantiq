<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Models;

use App\Modules\Access\Models\DomainEntitlement;
use App\Modules\Access\Models\RoleAssignment;
use App\Modules\Access\Support\RoleCode;
use App\Modules\Domains\Models\BusinessDomain;
use App\Modules\Platform\Models\User;
use App\Modules\Reviews\Support\DecisionBasis;
use App\Modules\Reviews\Support\ReviewDecision;
use App\Modules\Reviews\Support\ReviewKind;
use App\Modules\Reviews\Support\ReviewState;
use App\Modules\Reviews\Support\SupersededReason;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reviewable object inside one cycle. D-84.
 *
 * THE DECISION LIVES HERE rather than in a table of its own. It is 1:1 with a
 * terminal item and can never be more than one - terminal states are terminal
 * and there is no reopen - so a separate table would add a join to every read
 * and admit a state the machine forbids. The decision columns are WRITE-ONCE:
 * the service refuses to touch them unless the state is still pending, and
 * ReviewDecisionImmutabilityTest breaks if that check is removed.
 *
 * EXACTLY ONE OF role_assignment_id / domain_entitlement_id IS SET. MySQL 8.4
 * has no partial index that could express it, so the invariant is asserted in
 * the model and in AccessReviewItemShapeTest rather than claimed in a comment.
 */
final class AccessReviewItem extends Model
{
    protected $fillable = [
        'access_review_cycle_id',
        'kind',
        'subject_user_id',
        'role_assignment_id',
        'domain_entitlement_id',
        'business_domain_id',
        'role_code',
        'state',
        'due_at',
        'composition',
        'composition_fingerprint',
        'pending_decision',
        'decided_at',
        'decided_by_user_id',
        'decision_basis',
        'self_review',
        'superseded_reason',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ReviewKind::class,
            'state' => ReviewState::class,
            'role_code' => RoleCode::class,
            'decision_basis' => DecisionBasis::class,
            'superseded_reason' => SupersededReason::class,
            'pending_decision' => ReviewDecision::class,
            'due_at' => 'datetime',
            'decided_at' => 'datetime',
            'composition' => 'array',
            'self_review' => 'boolean',
        ];
    }

    /** @return BelongsTo<AccessReviewCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(AccessReviewCycle::class, 'access_review_cycle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    /** @return BelongsTo<RoleAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(RoleAssignment::class, 'role_assignment_id');
    }

    /** @return BelongsTo<DomainEntitlement, $this> */
    public function entitlement(): BelongsTo
    {
        return $this->belongsTo(DomainEntitlement::class, 'domain_entitlement_id');
    }

    /** @return BelongsTo<BusinessDomain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(BusinessDomain::class, 'business_domain_id');
    }

    /** @param Builder<AccessReviewItem> $query */
    public function scopePending(Builder $query): void
    {
        $query->where('state', ReviewState::Pending->value);
    }

    /**
     * Overdue is DERIVED, never stored: pending and past the due instant.
     *
     * Compared as an instant rather than as a local date, so the answer is the
     * same in every timezone - OverdueIsDeterministicTest breaks if it becomes
     * a date comparison.
     *
     * @param  Builder<AccessReviewItem>  $query
     */
    public function scopeOverdue(Builder $query): void
    {
        $query->where('state', ReviewState::Pending->value)->where('due_at', '<', now());
    }

    public function isOverdue(): bool
    {
        return $this->state === ReviewState::Pending
            && $this->due_at !== null
            && $this->due_at->lt(now());
    }

    /** The invariant, checkable rather than asserted. */
    public function hasExactlyOneReviewedObject(): bool
    {
        return ($this->role_assignment_id === null) !== ($this->domain_entitlement_id === null);
    }
}
