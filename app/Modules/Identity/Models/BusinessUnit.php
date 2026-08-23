<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use App\Modules\Identity\Concerns\BelongsToOrganisation;
use App\Modules\Platform\Enums\LifecycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A major organisational division. Feature ADM-003.
 *
 * A SCOPE, never a permission. It answers "which slice of the organisation is
 * this person part of" and later narrows what a domain entitlement covers. It
 * grants nothing by itself, and no authorization check may read it as though it
 * did.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $parent_id
 * @property LifecycleStatus $status
 */
class BusinessUnit extends Model
{
    use BelongsToOrganisation;

    /**
     * `code` is absent: VAL-BU-CODE-001 makes it the stable identifier, so a
     * change is a deliberate line of code rather than a posted field.
     */
    protected $fillable = [
        'name', 'parent_id', 'manager_user_id', 'cost_centre',
        'country', 'effective_from', 'effective_to', 'status', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => LifecycleStatus::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * Whether this unit may receive a new assignment.
     *
     * VAL-BU-INACTIVE-001. A disabled unit keeps everyone already in it -
     * history stays auditable - but takes nobody new.
     */
    public function acceptsAssignment(): bool
    {
        return $this->status === LifecycleStatus::Active;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    /**
     * The ancestors of this unit, nearest first.
     *
     * Bounded rather than recursive-until-done. A cycle should be impossible -
     * VAL-BU-LOOP-001 refuses to create one - but a traversal that trusts that
     * invariant absolutely will hang the request if it is ever violated by a
     * direct database edit, and a hung request is a worse failure than a
     * truncated breadcrumb.
     *
     * @return list<self>
     */
    public function ancestors(int $limit = 32): array
    {
        $chain = [];
        $current = $this->parent;

        while ($current !== null && count($chain) < $limit) {
            $chain[] = $current;
            $current = $current->parent;
        }

        return $chain;
    }

    /**
     * The full path, for a list that must show where a unit sits.
     */
    public function path(): string
    {
        $names = array_map(fn (self $unit): string => $unit->name, array_reverse($this->ancestors()));
        $names[] = $this->name;

        return implode(' / ', $names);
    }
}
