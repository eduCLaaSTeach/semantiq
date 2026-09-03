<?php

declare(strict_types=1);

namespace App\Modules\Domains\Models;

use App\Modules\Platform\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One period during which somebody was accountable for a domain.
 *
 * THIS TABLE IS THE SOURCE OF TRUTH for who owns a domain. `ended_at IS NULL`
 * means current; no such row means nobody owns it. Absence, not a NULL column.
 *
 * A row is NEVER deleted and NEVER updated in place. Changing owner ends the
 * open period and inserts the next, in one transaction, so the record of who
 * was accountable and when survives every later decision.
 *
 * assigned_at and ended_at are DATETIME, and there is NO uniqueness involving
 * assigned_at. P1-01 keyed team membership on (team_id, user_id, joined_at)
 * over DATE values, could not represent two periods in one day, and P1-03 paid
 * for that with a correction - and then production produced exactly that case
 * on its first day of use. The lesson is applied at the schema here rather than
 * learned a third time.
 *
 * BEING AN OWNER GRANTS NOTHING. This row says a person is accountable; it does
 * not say they may see anything.
 *
 * @property int $id
 * @property int $business_domain_id
 * @property int $user_id
 * @property Carbon $assigned_at
 * @property Carbon|null $ended_at
 */
final class DomainOwnership extends Model
{
    protected $fillable = ['business_domain_id', 'user_id', 'assigned_at', 'ended_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<BusinessDomain, $this> */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(BusinessDomain::class, 'business_domain_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }
}
