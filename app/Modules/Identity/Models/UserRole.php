<?php

declare(strict_types=1);

namespace App\Modules\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One additional role held by one person. Feature ADM-005.
 *
 * `users.role` remains the person's primary tier and is untouched by this.
 * These are the "additional roles" ADM-005 lists separately, and they are
 * additive only within the primary tier's ceiling.
 *
 * @property int $user_id
 * @property int $role_id
 */
class UserRole extends Model
{
    /** @var list<string> */
    protected $fillable = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AccessRole::class, 'role_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
