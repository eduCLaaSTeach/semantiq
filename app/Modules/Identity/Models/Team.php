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
 * A working team beneath a business unit. Feature ADM-004.
 *
 * A scope, never a permission - the same rule as `BusinessUnit`.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property LifecycleStatus $status
 */
class Team extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'name', 'description', 'business_unit_id', 'lead_user_id', 'status', 'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return ['status' => LifecycleStatus::class];
    }

    /** VAL-TEAM-BU-001: exactly one, and it is not nullable. */
    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lead_user_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** ADM-004: an inactive team cannot receive new users. */
    public function acceptsAssignment(): bool
    {
        return $this->status === LifecycleStatus::Active;
    }
}
