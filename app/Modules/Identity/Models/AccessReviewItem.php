<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Enums\BusinessDomain;
use App\Models\User;
use App\Modules\Identity\Enums\ReviewDecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One access grant awaiting a keep-or-revoke decision. Feature ADM-008.
 *
 * Two kinds of grant are reviewed, in one list rather than two, because a
 * reviewer works through decisions rather than through categories:
 *
 *   `role`        - an additional role from `user_roles`
 *   `entitlement` - a business domain from `domain_entitlements`
 *
 * A person's PRIMARY tier is deliberately not reviewable here. Changing a tier
 * has invariants of its own - the last System Administrator among them - and
 * putting it in a bulk decision screen would route around them.
 *
 * `subject_label` is the snapshot: what the grant was CALLED when the review
 * opened. It is what an auditor reads a year later, and it must not change when
 * the underlying role is renamed.
 *
 * @property string $subject_type
 * @property string $subject_key
 * @property string $subject_label
 * @property ReviewDecision $decision
 * @property bool $applied
 */
class AccessReviewItem extends Model
{
    public const TYPE_ROLE = 'role';

    public const TYPE_ENTITLEMENT = 'entitlement';

    /** Only the reviewer's own two inputs. Everything else is set by the service. */
    protected $fillable = ['decision', 'note'];

    protected function casts(): array
    {
        return [
            'decision' => ReviewDecision::class,
            'applied' => 'boolean',
            'decided_at' => 'datetime',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(AccessReview::class, 'access_review_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_user_id');
    }

    public function isRole(): bool
    {
        return $this->subject_type === self::TYPE_ROLE;
    }

    public function isEntitlement(): bool
    {
        return $this->subject_type === self::TYPE_ENTITLEMENT;
    }

    /**
     * The domain this item is about, when it is an entitlement.
     *
     * Null for a role item, and null for an entitlement whose domain is no
     * longer declared - a domain removed from the enum is not resurrected by a
     * historical row.
     */
    public function domain(): ?BusinessDomain
    {
        return $this->isEntitlement() ? BusinessDomain::tryFrom($this->subject_key) : null;
    }
}
