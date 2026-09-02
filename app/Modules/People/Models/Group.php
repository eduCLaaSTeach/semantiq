<?php

declare(strict_types=1);

namespace App\Modules\People\Models;

use App\Modules\Organisation\Models\Organisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An organisational label and a membership container. Nothing else.
 *
 * D-35: SemantIQ-owned, flat, no nesting, and NO ACCESS MEANING. Being in a
 * group grants nothing - not a domain, not a scope, not a sensitivity ceiling,
 * not management authority. P1-05 owns deciding whether groups ever participate
 * in access; this model must not anticipate that decision, which is why it has
 * no relationship, method or column that could be read as a grant.
 *
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property GroupStatus $status
 */
final class Group extends Model
{
    protected $fillable = ['organisation_id', 'name', 'code', 'description', 'status'];

    protected function casts(): array
    {
        return ['status' => GroupStatus::class];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<GroupMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(GroupMembership::class);
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }
}
